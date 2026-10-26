
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

// 取引IDを取得
$transaction_id = filter_input(
    INPUT_POST,
    'transaction_id',
    FILTER_VALIDATE_INT
);

if (
    $transaction_id === false ||
    $transaction_id === null ||
    $transaction_id <= 0
) {
    exit('取引IDが正しくありません。');
}

// DB接続
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 取引情報をロックして取得
    $stmt = $pdo->prepare(
        'SELECT buyer_id, transaction_status
        FROM buy
        WHERE transaction_id = :transaction_id
        FOR UPDATE'
    );

    $stmt->execute([
        'transaction_id' => $transaction_id
    ]);

    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new RuntimeException('取引情報が見つかりません。');
    }

    // 購入者本人か確認
    if ((int) $transaction['buyer_id'] !== $user_id) {
        throw new RuntimeException(
            'この取引を完了する権限がありません。'
        );
    }

    // 発送済みの場合だけ取引完了に変更
    if ($transaction['transaction_status'] !== '発送済み') {
        throw new RuntimeException(
            '発送済みの取引だけ完了できます。'
        );
    }

    // 取引状況と完了日時を更新
    $stmt = $pdo->prepare(
        'UPDATE buy
        SET
            transaction_status = :new_status,
            completed_at = CURRENT_TIMESTAMP
        WHERE transaction_id = :transaction_id
            AND buyer_id = :buyer_id
            AND transaction_status = :current_status'
    );

    $stmt->execute([
        'new_status' => '取引完了',
        'transaction_id' => $transaction_id,
        'buyer_id' => $user_id,
        'current_status' => '発送済み'
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            '取引完了の更新に失敗しました。'
        );
    }

    // 更新を確定
    $pdo->commit();

    // 取引詳細画面へ戻る
    header(
        'Location: transaction-detail.php?transaction_id=' .
        $transaction_id
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