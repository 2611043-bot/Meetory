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

$pdo = getPDO();


// ========================================
// ログイン確認
// ========================================

$my_user_id = $_SESSION['user_id'] ?? null;

if (!$my_user_id) {
    exit('ログインしてください。');
}

$my_user_id = (int)$my_user_id;


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

$partner_name = $partner['user_name'];
$partner_initial = mb_substr($partner_name, 0, 1, 'UTF-8');

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
    header('Location: message-room.php?user_id=' . $user_id);
    exit;
}

// ========================================
// トーク一覧（データベースから取得）
// 部分表示モードでは一覧は不要なので取得しない
// ========================================

if (!$is_partial) {

$sql = "
    SELECT
        m.message_id,
        partner.user_id,
        partner.user_name,
        partner.profile_image_type,
        partner.profile_image,
        m.content,
        m.message_type,
        m.sent_at

    FROM messages AS m

    INNER JOIN users AS partner
        ON partner.user_id = CASE
            WHEN m.sender_id = ?
            THEN m.receiver_id
            ELSE m.sender_id
        END

    WHERE
        (m.sender_id = ? OR m.receiver_id = ?)
        AND m.sender_id <> m.receiver_id

        AND m.message_id = (
            SELECT m2.message_id
            FROM messages AS m2

            WHERE
                (m2.sender_id = ? OR m2.receiver_id = ?)

                AND (
                    CASE
                        WHEN m2.sender_id = ?
                        THEN m2.receiver_id
                        ELSE m2.sender_id
                    END
                ) = (
                    CASE
                        WHEN m.sender_id = ?
                        THEN m.receiver_id
                        ELSE m.sender_id
                    END
                )

            ORDER BY
                m2.sent_at DESC,
                m2.message_id DESC

            LIMIT 1
        )

    ORDER BY
        m.sent_at DESC,
        m.message_id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id
]);

$talks = $stmt->fetchAll(PDO::FETCH_ASSOC);


// トーク一覧の表示用データを整える
foreach ($talks as &$talk) {

    // 相手の名前の最初の1文字
    $talk['initial'] = mb_substr(
        $talk['user_name'],
        0,
        1,
        'UTF-8'
    );

    // 最後のメッセージ
    if ($talk['message_type'] === 'goods') {
        $talk['last_message'] = '商品を紹介しました。';
    } else {
        $talk['last_message'] = $talk['content'];
    }

    // メッセージの日時
    $sent_time = strtotime($talk['sent_at']);

    if (date('Y-m-d', $sent_time) === date('Y-m-d')) {
        $talk['time'] = date('H:i', $sent_time);
    } else {
        $talk['time'] = date('m/d', $sent_time);
    }

    // 未読管理はまだ実装していないため0
    $talk['unread'] = 0;
}

unset($talk);

} // if (!$is_partial)



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
        g.price
    FROM messages AS m

    LEFT JOIN goods AS g
        ON m.goods_id = g.goods_id

    WHERE
        (
            m.sender_id = :my_id_1
            AND m.receiver_id = :partner_id_1
        )
        OR
        (
            m.sender_id = :partner_id_2
            AND m.receiver_id = :my_id_2
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


// ========================================
// 共通ヘッダー読み込み
// 部分表示モードではヘッダー・フッターは出力しない
// ========================================

if (!$is_partial) {
    require_once '../../meetory-header.php';
}
?>

<?php if (!$is_partial): ?>
<link rel="stylesheet"
        href="/Meetory/meetory/assets/css/message-room.css"
>
<?php endif; ?>


<?php if (!$is_partial): ?>
<!-- ========================================
    メッセージ画面
======================================== -->

<div class="message-layout-room">

    <!-- 左側：トーク一覧 -->
    <aside class="room-sidebar">

        <div class="room-sidebar-header">
            <h2>メッセージ</h2>
        </div>

        <!-- トーク検索 -->
        <div class="room-sidebar-search">
            <input
                type="search"
                id="roomTalkSearch"
                placeholder="トークを検索..."
            >
        </div>

        <!-- トーク一覧 -->
        <div class="room-talk-list">

            <?php foreach ($talks as $talk): ?>

                <a
                    href="message-room.php?user_id=<?= (int)$talk['user_id'] ?>"
                    class="room-talk-item <?= (int)$talk['user_id'] === $user_id ? 'active' : '' ?>"
                >

                    <div class="room-talk-avatar">
                        <?= renderProfileAvatar($talk, '', '') ?>
                    </div>

                    <div class="room-talk-info">

                        <div class="room-talk-name-row">

                            <span>
                                <?= htmlspecialchars(
                                    $talk['user_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                            <small>
                                <?= htmlspecialchars(
                                    $talk['time'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </small>

                        </div>

                        <div class="room-talk-message-row">

                            <p>
                                <?= htmlspecialchars(
                                    $talk['last_message'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                            <?php if ($talk['unread'] > 0): ?>

                                <span class="room-unread">
                                    <?= (int)$talk['unread'] ?>
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    </aside>

<?php endif; ?>


    <!-- 右側：トーク画面 -->
    <section class="message-room<?= $is_partial ? ' room-partial' : '' ?>">

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

                                    <button
                                        type="button"
                                        class="product-card-header"
                                        aria-expanded="false"
                                    >

                                        <span>
                                            <i class="fa-solid fa-box"></i>
                                            商品情報
                                        </span>

                                        <i class="fa-solid fa-chevron-down product-arrow"></i>

                                    </button>

                                    <div class="product-card-body" hidden>

                                        <div class="product-information">

                                            <div class="product-details">

                                                <h3>
                                                    <?= htmlspecialchars(
                                                        $message['goods_name'] ?? '商品情報なし',
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </h3>

                                                <p class="product-price">
                                                    ¥<?= number_format(
                                                        (int)($message['price'] ?? 0)
                                                    ) ?>
                                                </p>

                                                <?php if (!empty($message['goods_id'])): ?>

                                                    <a
                                                        href="../goods/goods-detail.php?goods_id=<?= (int)$message['goods_id'] ?>"
                                                        class="product-detail-link"
                                                    >
                                                        商品詳細を見る
                                                    </a>

                                                <?php endif; ?>

                                            </div>

                                        </div>

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

<?php if (!$is_partial): ?>
</div>


<script
    src="/Meetory/meetory/assets/js/message-room.js"
    defer
></script>


</main>

<?php require_once '../../meetory-footer.php'; ?>
<?php endif; ?>