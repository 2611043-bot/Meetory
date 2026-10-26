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

// 共通処理を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// URLから取引IDを取得
$transaction_id = filter_input(
    INPUT_GET,
    'transaction_id',
    FILTER_VALIDATE_INT
);

// 取引IDの確認
if (
    $transaction_id === false ||
    $transaction_id === null ||
    $transaction_id <= 0
) {
    exit('取引IDが正しくありません。');
}

// ログイン中のユーザー自身の購入情報だけを取得
$sql = '
    SELECT
        transaction_id,
        total_price,
        purchased_at,
        payment_method,
        transaction_status,
        address
    FROM buy
    WHERE transaction_id = ?
        AND buyer_id = ?
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id, $user_id]);

$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

// 購入情報が見つからない場合
if ($purchase === false) {
    exit('購入情報が見つかりません。');
}

?>

<?php require '../../meetory-header.php'; ?>

<main>

    <h1>購入完了</h1>

    <p>ご購入ありがとうございます！</p>

    <h2>購入情報</h2>

    <p>
        取引ID：
        <?= (int) $purchase['transaction_id'] ?>
    </p>

    <p>
        合計金額：
        <?= number_format((int) $purchase['total_price']) ?>円
    </p>

    <p>
        購入日時：
        <?= htmlspecialchars(
            $purchase['purchased_at'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        支払い方法：
        <?= htmlspecialchars(
            $purchase['payment_method'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        配送先：
        <?= htmlspecialchars(
            $purchase['address'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        取引状況：
        <?= htmlspecialchars(
            $purchase['transaction_status'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        ※現在はテスト用のため、実際の決済や送金は行われません。
    </p>

    <p>
        <a href="../goods/goods-list.php">
            商品一覧へ戻る
        </a>
    </p>

</main>

<?php require '../../meetory-footer.php'; ?>