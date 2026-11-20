<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理
require_once '../../meetory-functions.php';
require_once '../notifications/create-notification.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];
$is_partial = ($_GET['partial'] ?? '') === '1';
$sender_name = $_SESSION['user_name'] ?? '';

// GETまたはPOSTからIDを取得
$source = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? INPUT_POST
    : INPUT_GET;

$transaction_id = filter_input(
    $source,
    'transaction_id',
    FILTER_VALIDATE_INT
);

$goods_id = filter_input(
    $source,
    'goods_id',
    FILTER_VALIDATE_INT
);

$partner_id = filter_input(
    $source,
    'partner_id',
    FILTER_VALIDATE_INT
);

// IDの入力チェック
if (
    $transaction_id !== false &&
    $transaction_id !== null &&
    $transaction_id > 0 &&
    $goods_id !== false &&
    $goods_id !== null &&
    $goods_id > 0
) {
    http_response_code(400);
    exit('取引IDと商品IDを同時に指定することはできません。');
}

// 購入前DMか、購入後の取引メッセージか判定
$is_pre_purchase = (
    $goods_id !== false &&
    $goods_id !== null &&
    $goods_id > 0
);

// IDの入力チェック
if (
    $partner_id === false ||
    $partner_id === null ||
    $partner_id <= 0 ||
    $partner_id === $user_id
) {
    exit('会話相手が正しくありません。');
}

if ($is_pre_purchase) {
    // =========================
    // 購入前DM
    // =========================

    // 商品情報と出品者IDを取得
    $sql = '
        SELECT goods_id, goods_name, seller_id
        FROM goods
        WHERE goods_id = ?
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);
    $goods = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$goods) {
        http_response_code(404);
        exit('商品が見つかりません。');
    }

    $seller_id = (int) $goods['seller_id'];

    if ($seller_id === $user_id) {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM messages
             WHERE transaction_id IS NULL
               AND goods_id = :goods_id
               AND (
                   (sender_id = :seller_id AND receiver_id = :buyer_id)
                   OR
                   (sender_id = :buyer_id AND receiver_id = :seller_id)
               )
             LIMIT 1'
        );
        $stmt->execute([
            ':goods_id' => $goods_id,
            ':seller_id' => $user_id,
            ':buyer_id' => $partner_id,
        ]);

        if (!$stmt->fetchColumn()) {
            http_response_code(403);
            exit('この商品の会話を開く権限がありません。');
        }
    } elseif ($seller_id !== $partner_id) {
        http_response_code(403);
        exit('この商品の出品者とだけ会話できます。');
    }

    $page_title = '出品者とのDM';
    $goods_names = [$goods['goods_name']];

} else {
    // =========================
    // 購入後の取引メッセージ
    // =========================

    if (
        $transaction_id === false ||
        $transaction_id === null ||
        $transaction_id <= 0
    ) {
        exit('取引IDが正しくありません。');
    }

    // 購入者を取得
    $sql = '
        SELECT buyer_id
        FROM buy
        WHERE transaction_id = ?
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transaction_id]);
    $buyer_id = $stmt->fetchColumn();

    if ($buyer_id === false) {
        exit('取引が見つかりません。');
    }

    $buyer_id = (int) $buyer_id;

    // この取引に含まれる出品者IDを取得
    $sql = '
        SELECT DISTINCT g.seller_id
        FROM buy_items AS bi
        INNER JOIN goods AS g
            ON bi.goods_id = g.goods_id
        WHERE bi.transaction_id = ?
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transaction_id]);

    $seller_ids = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    // 会話相手が取引関係者か確認
    if ($user_id === $buyer_id) {
        $is_valid_conversation = in_array(
            $partner_id,
            $seller_ids,
            true
        );
    } else {
        $is_valid_conversation = (
            in_array($user_id, $seller_ids, true) &&
            $partner_id === $buyer_id
        );
    }

    if (!$is_valid_conversation) {
        http_response_code(403);
        exit('この取引相手と会話する権限がありません。');
    }

    // 取引内の商品名を取得
    $seller_id = ($user_id === $buyer_id)
        ? $partner_id
        : $user_id;

    $sql = '
        SELECT g.goods_name
        FROM buy_items AS bi
        INNER JOIN goods AS g
            ON bi.goods_id = g.goods_id
        WHERE bi.transaction_id = ?
            AND g.seller_id = ?
        ORDER BY bi.buy_item_id ASC
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transaction_id, $seller_id]);

    $goods_names = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $page_title = '取引メッセージ';
}

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// メッセージ送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        http_response_code(403);
        exit('不正なリクエストです。画面を開き直してください。');
    }

    $content = $_POST['content'] ?? '';

    if (!is_string($content)) {
        exit('メッセージの形式が正しくありません。');
    }

    $content = trim($content);

    if ($content === '') {
        exit('メッセージを入力してください。');
    }

    if (mb_strlen($content, 'UTF-8') > 2000) {
        exit('メッセージは2000文字以内で入力してください。');
    }

    if ($is_pre_purchase) {
        // 購入前DM：取引IDはNULL、商品IDを保存
        try {
            $pdo->beginTransaction();

            $sql = '
                INSERT INTO messages (
                    transaction_id,
                    sender_id,
                    receiver_id,
                    content,
                    goods_id
                )
                VALUES (NULL, ?, ?, ?, ?)
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $user_id,
                $partner_id,
                $content,
                $goods_id
            ]);

            $messageId = (int) $pdo->lastInsertId();

            // 受信者へ通知を作成（重複は作成しない）
            $stmtNotify = $pdo->prepare(
                'SELECT COUNT(*) FROM notifications
                 WHERE user_id = ? AND type = ? AND related_id = ?'
            );
            $stmtNotify->execute([$partner_id, 'message', $messageId]);
            $alreadyNotified = $stmtNotify->fetchColumn() > 0;

            if (!$alreadyNotified) {
                createNotification(
                    $pdo,
                    $partner_id,
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
            error_log('message-list: メッセージ送信エラー: ' . $e->getMessage());
            exit('メッセージの送信に失敗しました。');
        }

        if ($is_partial) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header(
            'Location: message-center.php?goods_id='
            . $goods_id
            . '&partner_id='
            . $partner_id
        );
        exit;

    } else {
        // 購入後の取引メッセージ
        try {
            $pdo->beginTransaction();

            $sql = '
                INSERT INTO messages (
                    transaction_id,
                    sender_id,
                    receiver_id,
                    content
                )
                VALUES (?, ?, ?, ?)
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $transaction_id,
                $user_id,
                $partner_id,
                $content
            ]);

            $messageId = (int) $pdo->lastInsertId();

            // 受信者へ通知を作成（重複は作成しない）
            $stmtNotify = $pdo->prepare(
                'SELECT COUNT(*) FROM notifications
                 WHERE user_id = ? AND type = ? AND related_id = ?'
            );
            $stmtNotify->execute([$partner_id, 'message', $messageId]);
            $alreadyNotified = $stmtNotify->fetchColumn() > 0;

            if (!$alreadyNotified) {
                createNotification(
                    $pdo,
                    $partner_id,
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
            error_log('message-list: メッセージ送信エラー: ' . $e->getMessage());
            exit('メッセージの送信に失敗しました。');
        }

        if ($is_partial) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header(
            'Location: message-center.php?transaction_id='
            . $transaction_id
            . '&partner_id='
            . $partner_id
        );
        exit;
    }
}

$stmt = $pdo->prepare(
    'SELECT user_id, user_name, profile_image_type, profile_image
     FROM users
     WHERE user_id = ?'
);
$stmt->execute([$partner_id]);
$partner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partner) {
    exit('会話相手が見つかりません。');
}

$partner_name = $partner['user_name'];

// メッセージ取得
if ($is_pre_purchase) {
    // 購入前DMは商品IDとユーザー2人で絞り込む
    $sql = '
        SELECT
            m.message_id,
            m.sender_id,
            m.content,
            m.sent_at,
            u.user_name AS sender_name
        FROM messages AS m
        INNER JOIN users AS u
            ON m.sender_id = u.user_id
        WHERE m.transaction_id IS NULL
            AND m.goods_id = ?
            AND (
                (m.sender_id = ? AND m.receiver_id = ?)
                OR
                (m.sender_id = ? AND m.receiver_id = ?)
            )
        ORDER BY m.sent_at ASC, m.message_id ASC
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $goods_id,
        $user_id,
        $partner_id,
        $partner_id,
        $user_id
    ]);

} else {
    // 購入後の取引メッセージ
    $sql = '
        SELECT
            m.message_id,
            m.sender_id,
            m.content,
            m.sent_at,
            u.user_name AS sender_name
        FROM messages AS m
        INNER JOIN users AS u
            ON m.sender_id = u.user_id
        WHERE m.transaction_id = ?
            AND (
                (m.sender_id = ? AND m.receiver_id = ?)
                OR
                (m.sender_id = ? AND m.receiver_id = ?)
            )
        ORDER BY m.sent_at ASC, m.message_id ASC
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $transaction_id,
        $user_id,
        $partner_id,
        $partner_id,
        $user_id
    ]);
}

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$is_partial) {
    $contextQuery = $is_pre_purchase
        ? ['goods_id' => $goods_id, 'partner_id' => $partner_id]
        : ['transaction_id' => $transaction_id, 'partner_id' => $partner_id];
    header('Location: message-center.php?' . http_build_query($contextQuery));
    exit;
}

?>

<section class="message-room room-partial context-room">
    <header class="room-header">
        <a href="message-center.php" class="room-back" aria-label="トーク一覧に戻る">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="room-avatar">
            <?= renderProfileAvatar($partner, '', '') ?>
        </div>
        <div class="room-partner">
            <h1><?= htmlspecialchars($partner_name, ENT_QUOTES, 'UTF-8') ?></h1>
            <span>
                <?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?>
                <?php if (!$is_pre_purchase): ?> ・取引 #<?= (int) $transaction_id ?><?php endif; ?>
            </span>
        </div>
    </header>

    <?php if ($is_pre_purchase): ?>
        <div class="room-context-meta">
            商品：
            <a href="../goods/goods-detail.php?goods_id=<?= (int) $goods_id ?>">
                <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    <?php elseif (!empty($goods_names)): ?>
        <div class="room-context-meta">
            <span>取引商品：</span>
            <?php foreach ($goods_names as $goods_name): ?>
                <span><?= htmlspecialchars($goods_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="room-messages" id="roomMessages" aria-live="polite">
        <?php if (empty($messages)): ?>
            <p class="empty-message">まだメッセージはありません。</p>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <?php
                $isSent = (int) $message['sender_id'] === $user_id;
                $messageTime = date('H:i', strtotime($message['sent_at']));
                ?>
                <div class="message-row <?= $isSent ? 'sent' : 'received' ?>">
                    <?php if (!$isSent): ?>
                        <div class="message-avatar">
                            <?= renderProfileAvatar($partner, '', '') ?>
                        </div>
                    <?php endif; ?>
                    <div class="message-content">
                        <?php if (!$isSent): ?>
                            <div class="message-sender">
                                <?= htmlspecialchars($message['sender_name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>
                        <div class="message-bubble">
                            <?= nl2br(htmlspecialchars($message['content'], ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                        <span class="message-time">
                            <?= htmlspecialchars($messageTime, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form
        class="room-input-area"
        id="contextMessageForm"
        data-ajax-message-form
        method="post"
    >
        <?php if ($is_pre_purchase): ?>
            <input type="hidden" name="goods_id" value="<?= (int) $goods_id ?>">
        <?php else: ?>
            <input type="hidden" name="transaction_id" value="<?= (int) $transaction_id ?>">
        <?php endif; ?>
        <input type="hidden" name="partner_id" value="<?= (int) $partner_id ?>">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >
        <input
            type="text"
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