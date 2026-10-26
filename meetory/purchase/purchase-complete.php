<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../user/login.php');
    exit;
}

require_once '../../meetory-functions.php';

$pdo = getPDO();

// URLから取引IDを取得
$transaction_id = filter_input(
    INPUT_GET,
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

// ログイン中のユーザー自身の購入情報だけ取得
$stmt = $pdo->prepare(
    'SELECT transaction_id, total_price, purchased_at
    FROM buy
    WHERE transaction_id = :transaction_id
        AND buyer_id = :buyer_id'
);

$stmt->execute([
    'transaction_id' => $transaction_id,
    'buyer_id' => (int) $_SESSION['user_id']
]);

$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    exit('購入情報が見つかりません。');
}
?>

<?php require_once '../../meetory-header.php'; ?>

<h1>購入が完了しました！</h1>

<p>ご購入ありがとうございます。</p>

<p>
    取引ID：
    <?= (int) $purchase['transaction_id'] ?>
</p>

<p>
    購入金額：
    <?= number_format((int) $purchase['total_price']) ?>円
</p>

<p>
    購入日時：
    <?= htmlspecialchars($purchase['purchased_at'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p>
    ※テスト用のため、実際の決済や送金は行われていません。
</p>

<p>
    <a href="../goods/goods-list.php">商品一覧に戻る</a>
</p>

<?php require_once '../../meetory-footer.php'; ?>