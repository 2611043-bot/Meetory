<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// POST以外のアクセスを拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
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

// CSRFトークン確認
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。画面を開き直してください。');
}

// 取引ID・出品者IDを取得
$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
$seller_id = filter_input(INPUT_POST, 'seller_id', FILTER_VALIDATE_INT);

if (
    $transaction_id === false || $transaction_id === null || $transaction_id <= 0 ||
    $seller_id === false || $seller_id === null || $seller_id <= 0
) {
    exit('指定が正しくありません。');
}

// DB接続
require_once '../../meetory-functions.php';
require_once '../notifications/create-notification.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 取引情報をロックして取得（購入者本人のみ）
    $stmt = $pdo->prepare(
        'SELECT transaction_id, buyer_id, transaction_status, cancelled_at
        FROM buy
        WHERE transaction_id = :transaction_id
          AND buyer_id = :buyer_id
        FOR UPDATE'
    );
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'buyer_id' => $user_id
    ]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new RuntimeException('この取引を受取確認する権限がありません。');
    }

    // キャンセル済みは受取確認できない
    if (!empty($transaction['cancelled_at'])) {
        throw new RuntimeException('キャンセル済みの取引は受取確認できません。');
    }

    // 取引完了済みは再実行できない
    if ($transaction['transaction_status'] === '取引完了') {
        throw new RuntimeException('この取引はすでに完了しています。');
    }

    // 対象の配送情報をロックして取得
    $stmt = $pdo->prepare(
        'SELECT delivery_id, delivery_status
        FROM deliveries
        WHERE transaction_id = :transaction_id
          AND seller_id = :seller_id
        FOR UPDATE'
    );
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'seller_id' => $seller_id
    ]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        throw new RuntimeException('配送情報が見つかりません。');
    }

    // 発送済みのみ受取確認可能（二重実行防止）
    if ($delivery['delivery_status'] !== 'shipped') {
        throw new RuntimeException('この配送情報はすでに受取確認済みです。');
    }

    // 配送ステータスを受取確認済みに変更
    $stmt = $pdo->prepare(
        'UPDATE deliveries
        SET delivery_status = :new_status,
            received_at = CURRENT_TIMESTAMP
        WHERE delivery_id = :delivery_id
          AND delivery_status = :current_status'
    );
    $stmt->execute([
        'new_status' => 'received',
        'delivery_id' => (int) $delivery['delivery_id'],
        'current_status' => 'shipped'
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('受取確認を更新できませんでした。');
    }

    // 取引内の全出品者の配送情報がすべて受取確認済みになった場合のみ取引を完了にする
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT g.seller_id) AS total_seller_count
        FROM buy_items AS bi
        INNER JOIN goods AS g
            ON bi.goods_id = g.goods_id
        WHERE bi.transaction_id = :transaction_id'
    );
    $stmt->execute(['transaction_id' => $transaction_id]);
    $totalSellerCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total_seller_count'];

    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT d.seller_id) AS received_count
        FROM deliveries AS d
        WHERE d.transaction_id = :transaction_id
          AND d.delivery_status = :received_status'
    );
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'received_status' => 'received'
    ]);
    $receivedCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['received_count'];

    if ($totalSellerCount > 0 && $receivedCount >= $totalSellerCount) {
        $stmt = $pdo->prepare(
            'UPDATE buy
            SET transaction_status = :new_status,
                completed_at = CURRENT_TIMESTAMP
            WHERE transaction_id = :transaction_id
              AND transaction_status = :current_status'
        );
        $stmt->execute([
            'new_status' => '取引完了',
            'transaction_id' => $transaction_id,
            'current_status' => '発送済み'
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('取引完了の更新に失敗しました。');
        }
    }

    // 更新を確定
    $pdo->commit();

    // 出品者へ受取通知を作成（重複は作成しない）
    $stmtNotify = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications
         WHERE user_id = ? AND type = ? AND related_id = ?'
    );
    $stmtNotify->execute([$seller_id, 'received', $transaction_id]);
    $alreadyNotified = $stmtNotify->fetchColumn() > 0;

    if (!$alreadyNotified) {
        createNotification(
            $pdo,
            $seller_id,
            'received',
            '商品が受け取られました',
            '出品した商品が受け取られました',
            $transaction_id
        );
    }

    // 配送情報画面へ戻る
    header(
        'Location: shipping-detail.php?transaction_id=' .
        $transaction_id .
        '&message=' . urlencode('受取確認を完了しました。')
    );
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit('データベースエラーが発生しました。');

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit(
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );

} catch (Throwable $e) {

    error_log($e->getMessage());

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit('予期しないエラーが発生しました。');
}
