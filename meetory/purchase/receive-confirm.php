<?php

// セッションを開始
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

// 共通処理・DB接続を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// 取引ID・出品者IDを取得
$transaction_id = filter_input(INPUT_GET, 'transaction_id', FILTER_VALIDATE_INT);
$seller_id = filter_input(INPUT_GET, 'seller_id', FILTER_VALIDATE_INT);

if (
    $transaction_id === false || $transaction_id === null || $transaction_id <= 0 ||
    $seller_id === false || $seller_id === null || $seller_id <= 0
) {
    exit('指定が正しくありません。');
}

// 取引情報を取得（購入者本人のみアクセス可能）
$stmt = $pdo->prepare(
    'SELECT
        b.transaction_id,
        b.buyer_id,
        b.transaction_status,
        b.cancelled_at,
        b.purchased_at
    FROM buy AS b
    WHERE b.transaction_id = :transaction_id
      AND b.buyer_id = :buyer_id'
);
$stmt->execute([
    'transaction_id' => $transaction_id,
    'buyer_id' => $user_id
]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    exit('この取引を受取確認する権限がありません。');
}

// キャンセル済みは受取確認できない
if (!empty($transaction['cancelled_at'])) {
    exit('キャンセル済みの取引は受取確認できません。');
}

// 取引完了済みは再実行できない
if ($transaction['transaction_status'] === '取引完了') {
    exit('この取引はすでに完了しています。');
}

// 対象の配送情報を取得
$stmt = $pdo->prepare(
    'SELECT
        d.delivery_id,
        d.carrier,
        d.carrier_other,
        d.tracking_number,
        d.delivery_status,
        d.shipped_at,
        g.goods_name,
        s.user_name AS seller_name
    FROM deliveries AS d
    INNER JOIN buy_items AS bi
        ON bi.transaction_id = d.transaction_id
    INNER JOIN goods AS g
        ON g.goods_id = bi.goods_id
    INNER JOIN users AS s
        ON s.user_id = d.seller_id
    WHERE d.transaction_id = :transaction_id
      AND d.seller_id = :seller_id
      AND g.seller_id = :seller_id
    LIMIT 1'
);
$stmt->execute([
    'transaction_id' => $transaction_id,
    'seller_id' => $seller_id
]);
$delivery = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$delivery) {
    exit('配送情報が見つかりません。');
}

// 発送済みのみ受取確認可能（二重実行防止）
if ($delivery['delivery_status'] !== 'shipped') {
    exit('この配送情報はすでに受取確認済みです。');
}

// 発送待ちの配送情報は受取確認できない
if ($delivery['delivery_status'] === 'awaiting_shipment') {
    exit('発送済みの配送情報だけ受取確認できます。');
}

?>

<?php require '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/shipping.css">
<link rel="stylesheet" href="../assets/css/transaction-detail.css">

<main>

    <h1>受取確認</h1>

    <div class="receive-confirm-box">
        <p>
            取引ID：
            <?= (int) $transaction['transaction_id'] ?>
        </p>

        <p>
            商品名：
            <?= htmlspecialchars($delivery['goods_name'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            出品者：
            <?= htmlspecialchars($delivery['seller_name'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            配送業者：
            <?= htmlspecialchars(
                $delivery['carrier'] === 'その他'
                    ? ($delivery['carrier_other'] ?? 'その他')
                    : $delivery['carrier'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <p>
            追跡番号：
            <?= htmlspecialchars($delivery['tracking_number'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            発送日時：
            <?= htmlspecialchars(
                date('Y年m月d日 H:i:s', strtotime($delivery['shipped_at'])),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <p class="receive-warning">この取引の受取確認を行います。よろしいですか？<br>受取確認すると配送ステータスが「受取確認済み」に変更されます。</p>

        <form action="receive-process.php" method="post" class="receive-confirm-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="transaction_id" value="<?= (int) $transaction_id ?>">
            <input type="hidden" name="seller_id" value="<?= (int) $seller_id ?>">
            <p>
                <button type="submit" class="btn-receive">受取確認を実行する</button>
            </p>
        </form>

        <p>
            <a href="shipping-detail.php?transaction_id=<?= (int) $transaction_id ?>" class="btn-cancel">受取確認をやめる</a>
        </p>
    </div>

</main>

<?php require '../../meetory-footer.php'; ?>
