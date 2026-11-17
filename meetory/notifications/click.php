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

$pdo = getPDO();
$user_id = (int) $_SESSION['user_id'];

// 通知IDを取得・検証
$notification_id = filter_input(INPUT_GET, 'notification_id', FILTER_VALIDATE_INT);

if (
    $notification_id === false ||
    $notification_id === null ||
    $notification_id <= 0
) {
    header('Location: index.php');
    exit;
}

// 通知の種類と関連IDを取得（所有者確認も兼ねる）
$stmt = $pdo->prepare(
    'SELECT type, related_id
     FROM notifications
     WHERE notification_id = :notification_id
       AND user_id = :user_id
     LIMIT 1'
);
$stmt->execute([
    ':notification_id' => $notification_id,
    ':user_id' => $user_id,
]);

$notification = $stmt->fetch(PDO::FETCH_ASSOC);

// 存在しない通知または他ユーザーの通知
if (!$notification) {
    header('Location: index.php');
    exit;
}

$type = $notification['type'];
$relatedId = $notification['related_id'];

// 通知を既読にする（未読の場合のみ）
$stmtRead = $pdo->prepare(
    'UPDATE notifications
     SET is_read = 1
     WHERE notification_id = :notification_id
       AND user_id = :user_id
       AND is_read = 0'
);
$stmtRead->execute([
    ':notification_id' => $notification_id,
    ':user_id' => $user_id,
]);

// 通知種類に応じた遷移先を決定
$redirectUrl = 'index.php';

switch ($type) {

    case 'message':
        // related_id = message_id。メッセージの相手ユーザーIDを取得して会話画面へ。
        $stmtMsg = $pdo->prepare(
            'SELECT sender_id, receiver_id
             FROM messages
             WHERE message_id = :message_id
               AND (sender_id = :user_id OR receiver_id = :user_id)
             LIMIT 1'
        );
        $stmtMsg->execute([
            ':message_id' => $relatedId,
            ':user_id' => $user_id,
        ]);
        $message = $stmtMsg->fetch(PDO::FETCH_ASSOC);

        if ($message) {
            $partnerId = ((int) $message['sender_id'] === $user_id)
                ? (int) $message['receiver_id']
                : (int) $message['sender_id'];
            $redirectUrl = '../messages/message-room.php?user_id=' . $partnerId;
        }
        break;

    case 'purchase':
    case 'shipping':
    case 'received':
    case 'review':
        // related_id = transaction_id。取引詳細画面へ。
        // 取引の当事者（購入者または出品者）か確認してから遷移。
        $stmtTx = $pdo->prepare(
            'SELECT buyer_id
             FROM buy
             WHERE transaction_id = :transaction_id
             LIMIT 1'
        );
        $stmtTx->execute([':transaction_id' => $relatedId]);
        $transaction = $stmtTx->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            $buyerId = (int) $transaction['buyer_id'];
            // 出品者かどうか確認
            $stmtSeller = $pdo->prepare(
                'SELECT COUNT(*) AS cnt
                 FROM buy_items AS bi
                 INNER JOIN goods AS g ON bi.goods_id = g.goods_id
                 WHERE bi.transaction_id = :transaction_id
                   AND g.seller_id = :user_id'
            );
            $stmtSeller->execute([
                ':transaction_id' => $relatedId,
                ':user_id' => $user_id,
            ]);
            $isSeller = (int) $stmtSeller->fetchColumn() > 0;

            if ($buyerId === $user_id || $isSeller) {
                $redirectUrl = '../purchase/transaction-detail.php?transaction_id=' . $relatedId;
            }
        }
        break;

    case 'favorite':
    case 'price_down':
        // related_id = goods_id。商品詳細画面へ。
        // 商品が存在するか確認。
        $stmtGoods = $pdo->prepare(
            'SELECT goods_id FROM goods WHERE goods_id = :goods_id LIMIT 1'
        );
        $stmtGoods->execute([':goods_id' => $relatedId]);
        if ($stmtGoods->fetch(PDO::FETCH_ASSOC) !== false) {
            $redirectUrl = '../goods/goods-detail.php?goods_id=' . $relatedId;
        }
        break;

    case 'follow':
        // related_id = フォローしたユーザーID。プロフィール画面へ。
        // フォロー機能は未実装のため、関連ユーザーの存在確認のみ行いプロフィールへ。
        $stmtUser = $pdo->prepare(
            'SELECT user_id FROM users WHERE user_id = :user_id LIMIT 1'
        );
        $stmtUser->execute([':user_id' => $relatedId]);
        if ($stmtUser->fetch(PDO::FETCH_ASSOC) !== false) {
            $redirectUrl = '../user/profile.php?user_id=' . $relatedId;
        }
        break;

    case 'admin':
        // related_id = お知らせID。運営からのお知らせ詳細画面へ。
        // お知らせ機能は未実装のため、一覧へ戻す。
        $redirectUrl = 'index.php';
        break;

    default:
        $redirectUrl = 'index.php';
        break;
}

header('Location: ' . $redirectUrl);
exit;
