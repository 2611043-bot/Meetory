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

// 共通処理・DB接続
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// CSRFトークン
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// POSTのみ想定だがID検証は厳密に行う
$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
if ($transaction_id === false || $transaction_id === null || $transaction_id <= 0) {
    $transaction_id = filter_input(INPUT_GET, 'transaction_id', FILTER_VALIDATE_INT);
}

if ($transaction_id === false || $transaction_id === null || $transaction_id <= 0) {
    http_response_code(400);
    exit('取引IDが正しくありません。');
}

// 取引情報を取得
$sql = '
    SELECT
        b.transaction_id,
        b.buyer_id,
        b.transaction_status
    FROM buy AS b
    WHERE b.transaction_id = :transaction_id
';
$stmt = $pdo->prepare($sql);
$stmt->execute(['transaction_id' => $transaction_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('取引情報が見つかりません。');
}

// 取引に含まれる商品と出品者を取得
$stmt = $pdo->prepare('
    SELECT
        g.goods_id,
        g.goods_name,
        g.seller_id
    FROM buy_items AS bi
    INNER JOIN goods AS g ON bi.goods_id = g.goods_id
    WHERE bi.transaction_id = :transaction_id
    ORDER BY bi.buy_item_id ASC
');
$stmt->execute(['transaction_id' => $transaction_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    http_response_code(404);
    exit('取引に含まれる商品が見つかりません。');
}

// 本人確認（購入者、または取引内すべての商品の出品者本人）
$is_buyer = (int) $row['buyer_id'] === $user_id;
$is_seller_of_all = true;
$seller_ids = [];
foreach ($items as $item) {
    $sid = (int) $item['seller_id'];
    $seller_ids[$sid] = true;
    if ($sid !== $user_id) {
        $is_seller_of_all = false;
    }
}
$is_seller = $is_seller_of_all && count($seller_ids) === 1 && isset($seller_ids[$user_id]);

if (!$is_buyer && !$is_seller) {
    http_response_code(403);
    exit('この取引をキャンセルする権限がありません。');
}

// キャンセル可能条件（発送前＝購入済み、未完了、未キャンセル）
if ($row['transaction_status'] !== '購入済み') {
    http_response_code(400);
    exit('この取引はキャンセルできません。');
}

require_once '../../meetory-header.php';
?>
<link rel="stylesheet" href="../assets/css/transaction-detail.css">

<main>
    <h1>取引キャンセル確認</h1>

    <div class="cancel-confirm-box">
        <p><strong>取引ID：</strong><?= (int) $row['transaction_id'] ?></p>
        <p><strong>対象商品：</strong></p>
        <ul>
            <?php foreach ($items as $item): ?>
                <li><?= htmlspecialchars($item['goods_name'], ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="cancel-warning">この取引をキャンセルします。よろしいですか？<br>キャンセルすると対象の商品は再び購入可能になります。</p>

        <form action="cancel-process.php" method="post" class="cancel-confirm-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="transaction_id" value="<?= (int) $transaction_id ?>">
            <label for="cancel_reason"><strong>キャンセル理由（任意）</strong></label><br>
            <textarea id="cancel_reason" name="cancel_reason" maxlength="255" rows="3" style="width:100%;max-width:480px;"></textarea>
            <p>
                <button type="submit" class="cancel-btn-danger">キャンセルを実行する</button>
            </p>
        </form>

        <p>
            <a href="transaction-detail.php?transaction_id=<?= (int) $transaction_id ?>" class="cancel-btn-secondary">キャンセルをやめる</a>
        </p>
    </div>
</main>

<?php require_once '../../meetory-footer.php'; ?>
