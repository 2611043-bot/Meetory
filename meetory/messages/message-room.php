<?php
// ========================================
// 初期設定
// ========================================

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 共通ファイル読み込み
require_once '../../meetory-functions.php';
require_once '../notifications/create-notification.php';

$pdo = getPDO();


// ========================================
// ログイン確認
// ========================================

$my_user_id = $_SESSION['user_id'] ?? null;

if (!$my_user_id) {
    header('Location: ../user/login.php');
    exit;
}

$my_user_id = (int)$my_user_id;
$sender_name = $_SESSION['user_name'] ?? '';


// ========================================
// 部分表示モードの判定
// ========================================

// partial=1 の場合はヘッダー・フッター・トーク一覧などを出力せず、
// 右側の会話画面だけを返す（message-center.php から fetch で利用）
$is_partial = filter_input(INPUT_GET, 'partial', FILTER_VALIDATE_INT) === 1;


// ========================================
// トーク相手の確認
// ========================================

$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$user_id || $user_id <= 0 || $user_id === $my_user_id) {
    exit('相手のユーザーIDが正しくありません。');
}

// 相手のユーザー情報を取得
$sql = "
    SELECT
        user_id,
        user_name,
        profile_image_type,
        profile_image
    FROM users
    WHERE user_id = :user_id
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':user_id' => $user_id
]);

$partner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    exit('相手のユーザーが見つかりません。');
}

if (!$is_partial && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: message-center.php?user_id=' . $user_id);
    exit;
}

$partner_name = $partner['user_name'];

// ========================================
// 自分が出品した商品を取得
// ========================================

$sql = "
    SELECT goods_id, goods_name, price
    FROM goods
    WHERE seller_id = :seller_id
    AND is_sold = 0
    ORDER BY listed_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':seller_id' => $my_user_id
]);

$my_goods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// CSRF対策
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========================================
// メッセージ送信処理
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRFトークン確認
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        exit('不正なリクエストです。');
    }

    // 送信する種類を取得
    $action = $_POST['action'] ?? '';

    // メッセージ送信制限の確認
    if (!canSendMessage($pdo, $my_user_id)) {
        $suspension = isAccountSuspended($pdo, $my_user_id);
        if ($suspension !== null) {
            exit('アカウント停止中のためメッセージを送信できません。');
        }
        exit('メッセージ送信制限が適用されているためメッセージを送信できません。');
    }

    if (isBlockedEitherWay($pdo, $my_user_id, $user_id)) {
        exit('ブロック中のためメッセージを送信できません。');
    }

    // ------------------------------------
    // 通常メッセージ
    // ------------------------------------

    if ($action === 'text') {

        $content = trim($_POST['content'] ?? '');

        if ($content === '') {
            exit('メッセージを入力してください。');
        }

        if (mb_strlen($content, 'UTF-8') > 2000) {
            exit('メッセージは2000文字以内で入力してください。');
        }

        try {
            $pdo->beginTransaction();

            $sql = "
                INSERT INTO messages (
                    sender_id,
                    receiver_id,
                    content,
                    message_type
                ) VALUES (
                    :sender_id,
                    :receiver_id,
                    :content,
                    'text'
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':sender_id' => $my_user_id,
                ':receiver_id' => $user_id,
                ':content' => $content
            ]);

            $messageId = (int) $pdo->lastInsertId();

            // 受信者へ通知を作成（重複は作成しない）
            $stmtNotify = $pdo->prepare(
                'SELECT COUNT(*) FROM notifications
                 WHERE user_id = ? AND type = ? AND related_id = ?'
            );
            $stmtNotify->execute([$user_id, 'message', $messageId]);
            $alreadyNotified = $stmtNotify->fetchColumn() > 0;

            if (!$alreadyNotified) {
                createNotification(
                    $pdo,
                    $user_id,
                    'message',
                    '新しいメッセージ',
                    $sender_name . 'さんからメッセージが届きました。',
                    $messageId
                );
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('message-room: テキスト送信エラー: ' . $e->getMessage());
            exit('メッセージの送信に失敗しました。');
        }

    }

    // ------------------------------------
    // 商品カード
    // ------------------------------------

    elseif ($action === 'goods') {

        $goods_id = filter_input(
            INPUT_POST,
            'goods_id',
            FILTER_VALIDATE_INT
        );

        if (!$goods_id || $goods_id <= 0) {
            exit('商品IDが正しくありません。');
        }

        // 自分が出品した商品か確認
        $sql = "
            SELECT goods_id
            FROM goods
            WHERE goods_id = :goods_id
            AND seller_id = :seller_id
            AND is_sold = 0
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':goods_id' => $goods_id,
            ':seller_id' => $my_user_id
        ]);

        $goods = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$goods) {
            exit('商品が見つからない、売却済み、または送信する権限がありません。');
        }

        // 商品カードをDBに保存
        try {
            $pdo->beginTransaction();

            $sql = "
                INSERT INTO messages (
                    sender_id,
                    receiver_id,
                    content,
                    message_type,
                    goods_id
                ) VALUES (
                    :sender_id,
                    :receiver_id,
                    :content,
                    'goods',
                    :goods_id
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':sender_id' => $my_user_id,
                ':receiver_id' => $user_id,
                ':content' => '商品を紹介します。',
                ':goods_id' => $goods_id
            ]);

            $messageId = (int) $pdo->lastInsertId();

            // 受信者へ通知を作成（重複は作成しない）
            $stmtNotify = $pdo->prepare(
                'SELECT COUNT(*) FROM notifications
                 WHERE user_id = ? AND type = ? AND related_id = ?'
            );
            $stmtNotify->execute([$user_id, 'message', $messageId]);
            $alreadyNotified = $stmtNotify->fetchColumn() > 0;

            if (!$alreadyNotified) {
                createNotification(
                    $pdo,
                    $user_id,
                    'message',
                    '新しいメッセージ',
                    $sender_name . 'さんからメッセージが届きました。',
                    $messageId
                );
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('message-room: 商品送信エラー: ' . $e->getMessage());
            exit('メッセージの送信に失敗しました。');
        }

    } else {
        exit('送信内容が正しくありません。');
    }

    // 部分表示モードの場合はJSONを返す（ページ遷移させない）
    if ($is_partial) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'user_id' => $user_id
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 通常アクセス時は従来通りリダイレクト（二重送信防止）
    header('Location: message-center.php?user_id=' . $user_id);
    exit;
}

// ========================================
// 2人の間のメッセージを取得
// ========================================

$sql = "
    SELECT
        m.message_id,
        m.sender_id,
        m.receiver_id,
        m.content,
        m.message_type,
        m.goods_id,
        m.sent_at,
        g.goods_name,
        g.price,
        g.description,
        g.`condition`,
        g.category_id,
        c.category_name,
        gi.image_path AS goods_image
    FROM messages AS m

    LEFT JOIN goods AS g
        ON m.goods_id = g.goods_id
    LEFT JOIN categories AS c
        ON g.category_id = c.category_id
    LEFT JOIN (
        SELECT goods_id, image_path
        FROM goods_images
        WHERE display_order = 1
    ) AS gi
        ON g.goods_id = gi.goods_id

    WHERE
        (
            (
                m.sender_id = :my_id_1
                AND m.receiver_id = :partner_id_1
            )
            OR
            (
                m.sender_id = :partner_id_2
                AND m.receiver_id = :my_id_2
            )
        )
        AND m.transaction_id IS NULL
        AND (
            m.goods_id IS NULL
            OR m.message_type = 'goods'
        )

    ORDER BY
        m.sent_at ASC,
        m.message_id ASC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':my_id_1' => $my_user_id,
    ':partner_id_1' => $user_id,
    ':partner_id_2' => $user_id,
    ':my_id_2' => $my_user_id
]);

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>

<section class="message-room room-partial">

        <!-- トーク画面のヘッダー -->
        <div class="room-header">

            <a href="message-center.php" class="room-back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>

            <div class="room-avatar">
                <?= renderProfileAvatar($partner, '', '') ?>
            </div>

            <div class="room-partner">

                <h1>
                    <?= htmlspecialchars(
                        $partner_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h1>

                <span>トークルーム</span>

            </div>

        </div>


        <!-- メッセージ表示エリア -->
        <div class="room-messages" id="roomMessages">

            <?php if (empty($messages)): ?>

                <p class="empty-message">
                    まだメッセージはありません。
                </p>

            <?php else: ?>

                <?php foreach ($messages as $message): ?>

                    <?php
                    // 自分が送信したメッセージか判定
                    $is_sent = (int)$message['sender_id'] === $my_user_id;

                    // 送信時刻
                    $time = date(
                        'H:i',
                        strtotime($message['sent_at'])
                    );
                    ?>


                    <?php if ($message['message_type'] === 'goods'): ?>

                        <!-- 商品カード -->
                        <div class="product-message-row <?= $is_sent ? 'sent' : 'received' ?>">

                            <?php if (!$is_sent): ?>

                                <div class="message-avatar">
                                    <?= renderProfileAvatar($partner, '', '') ?>
                                </div>

                            <?php endif; ?>

                            <div class="product-message-content">

                                <div class="message-sender">
                                    <?= $is_sent
                                        ? '自分'
                                        : htmlspecialchars(
                                            $partner_name,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ?>
                                    ・商品情報
                                </div>

                                <div class="product-card">

<!-- 商品画像（クリックでモーダル表示） -->
                                <?php
                                $goods_image = $message['goods_image'] ?? '';
                                $is_valid_goods_image = is_string($goods_image)
                                    && preg_match(
                                        '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
                                        $goods_image
                                    )
                                    && is_file(__DIR__ . '/../../' . $goods_image);
                                ?>
                                <div class="product-card-image" data-image-src="<?= $is_valid_goods_image ? '/Meetory/' . htmlspecialchars($goods_image, ENT_QUOTES, 'UTF-8') : '' ?>" data-image-alt="<?= htmlspecialchars($message['goods_name'] ?? '商品画像', ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($is_valid_goods_image): ?>
                                        <img
                                            src="/Meetory/<?= htmlspecialchars($goods_image, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($message['goods_name'] ?? '商品画像', ENT_QUOTES, 'UTF-8') ?>"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fa-solid fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                    <!-- 商品情報 -->
                                    <div class="product-card-info">
                                        <a href="../goods/goods-detail.php?goods_id=<?= (int)$message['goods_id'] ?>" class="product-card-name-link">
                                            <h4 class="product-card-name">
                                                <?= htmlspecialchars(
                                                    $message['goods_name'] ?? '商品情報なし',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h4>
                                        </a>

                                        <p class="product-card-price">
                                            ¥<?= number_format((int)($message['price'] ?? 0)) ?>
                                        </p>

                                        <div class="product-card-meta">
                                            <?php if (!empty($message['category_name'])): ?>
                                                <span class="product-card-category">
                                                    <?= htmlspecialchars($message['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($message['condition'])): ?>
                                                <span class="product-card-condition">
                                                    <?= htmlspecialchars($message['condition'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <a href="../goods/goods-detail.php?goods_id=<?= (int)$message['goods_id'] ?>" class="product-detail-link">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            商品詳細を見る
                                        </a>
                                    </div>

                                </div>

                                <span class="message-time">
                                    <?= htmlspecialchars(
                                        $time,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>

                        </div>


                    <?php else: ?>

                        <!-- 通常メッセージ -->
                        <div class="message-row <?= $is_sent ? 'sent' : 'received' ?>">

                            <?php if (!$is_sent): ?>

                                <div class="message-avatar">
                                    <?= renderProfileAvatar($partner, '', '') ?>
                                </div>

                            <?php endif; ?>

                            <div class="message-content">

                                <?php if (!$is_sent): ?>

                                    <div class="message-sender">
                                        <?= htmlspecialchars(
                                            $partner_name,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                <?php endif; ?>

                                <div class="message-bubble">
                                    <?= nl2br(
                                        htmlspecialchars(
                                            $message['content'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ) ?>
                                </div>

                                <span class="message-time">
                                    <?= htmlspecialchars(
                                        $time,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>


<!-- 商品選択ボタン -->
<div class="product-picker-area">

    <button
        type="button"
        id="openProductPicker"
        aria-expanded="false"
    >
        <i class="fa-solid fa-box"></i>
        <span class="product-picker-toggle-label">商品を送る</span>
    </button>

    <!-- 商品一覧 -->
    <div id="productPicker" hidden>

        <h3>送信する商品を選択</h3>

        <?php if (empty($my_goods)): ?>

            <p>出品中の商品がありません。</p>

        <?php else: ?>

            <?php foreach ($my_goods as $goods): ?>

                <div class="product-picker-item">

                    <div>
                        <strong>
                            <?= htmlspecialchars(
                                $goods['goods_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <p>
                            <?= number_format((int)$goods['price']) ?>円
                        </p>
                    </div>

                    <form
                        method="POST"
                        class="product-send-form"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                $_SESSION['csrf_token'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="goods"
                        >

                        <input
                            type="hidden"
                            name="goods_id"
                            value="<?= (int)$goods['goods_id'] ?>"
                        >

                        <button type="submit">
                            送信
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>


        <!-- 通常メッセージ入力欄 -->
        <form
            class="room-input-area"
            id="messageForm"
            data-ajax-message-form
            method="POST"
        >

            <input
                type="hidden"
                name="action"
                value="text"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="text"
                id="messageInput"
                name="content"
                placeholder="メッセージを入力..."
                autocomplete="off"
                maxlength="2000"
                required
            >

            <button type="submit" aria-label="メッセージを送信">
                <i class="fa-solid fa-paper-plane"></i>
            </button>

        </form>

    </section>

    <!-- 画像拡大モーダル -->
    <div class="image-modal" id="imageModal" role="dialog" aria-modal="true" aria-label="商品画像拡大" hidden>
        <div class="image-modal-overlay" tabindex="-1"></div>
        <div class="image-modal-content">
            <button type="button" class="image-modal-close" aria-label="閉じる">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <img id="imageModalImg" src="" alt="">
        </div>
    </div>
