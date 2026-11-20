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

// 選択可能な配送業者
$carriers = [
    'ヤマト運輸' => 'ヤマト運輸',
    '日本郵便' => '日本郵便',
    '佐川急便' => '佐川急便',
    'その他' => 'その他'
];

// URLから取引IDを取得
$transaction_id = filter_input(INPUT_GET, 'transaction_id', FILTER_VALIDATE_INT);

if (
    $transaction_id === false ||
    $transaction_id === null ||
    $transaction_id <= 0
) {
    exit('取引IDが正しくありません。');
}

// 取引情報を取得
$stmt = $pdo->prepare(
    'SELECT
        b.transaction_id,
        b.buyer_id,
        b.transaction_status,
        b.cancelled_at,
        buyer.user_name AS buyer_name
    FROM buy AS b
    INNER JOIN users AS buyer
        ON b.buyer_id = buyer.user_id
    WHERE b.transaction_id = :transaction_id'
);

$stmt->execute(['transaction_id' => $transaction_id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    exit('取引情報が見つかりません。');
}

// キャンセル済みは発送できない
if (!empty($transaction['cancelled_at'])) {
    exit('この取引はキャンセル済みのため発送できません。');
}

// 購入済みのみ発送手続きが可能
if ($transaction['transaction_status'] !== '購入済み') {
    exit('この取引は発送手続きができない状態です。');
}

// この出品者の商品が含まれるか確認
$stmt = $pdo->prepare(
    'SELECT COUNT(*)
    FROM buy_items AS bi
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    WHERE bi.transaction_id = :transaction_id
      AND g.seller_id = :seller_id'
);
$stmt->execute([
    'transaction_id' => $transaction_id,
    'seller_id' => $user_id
]);
$is_seller = (int) $stmt->fetchColumn() > 0;

if (!$is_seller) {
    exit('この取引の発送手続きを行う権限がありません。');
}

// すでにこの出品者分の配送情報が登録済みの場合は重複登録不可
$stmt = $pdo->prepare(
    'SELECT delivery_status FROM deliveries
    WHERE transaction_id = :transaction_id AND seller_id = :seller_id'
);
$stmt->execute([
    'transaction_id' => $transaction_id,
    'seller_id' => $user_id
]);
$existingDelivery = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingDelivery) {
    exit('この取引の配送情報はすでに登録されています。');
}

// 取引に含まれる商品と購入者情報を取得
$stmt = $pdo->prepare(
    'SELECT
        g.goods_id,
        g.goods_name,
        g.seller_id,
        seller.user_name AS seller_name,
        bi.purchase_price
    FROM buy_items AS bi
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    INNER JOIN users AS seller
        ON g.seller_id = seller.user_id
    WHERE bi.transaction_id = :transaction_id
    ORDER BY bi.buy_item_id ASC'
);
$stmt->execute(['transaction_id' => $transaction_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php require '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/shipping.css">
<link rel="stylesheet" href="../assets/css/transaction-detail.css">

<main>

    <h1>発送情報の登録</h1>

    <p>
        取引ID：
        <?= (int) $transaction['transaction_id'] ?>
    </p>

    <p>下記の商品を発送します。配送業者と追跡番号を入力して「発送完了」ボタンを押してください。</p>

    <section>
        <h2>対象商品</h2>

        <?php if (empty($items)): ?>

            <p>商品情報が見つかりません。</p>

        <?php else: ?>

            <table class="shipping-list-table">
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th>出品者</th>
                        <th>購入時の価格</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($items as $item): ?>

                        <tr>
                            <td><?= htmlspecialchars($item['goods_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['seller_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= number_format((int) $item['purchase_price']) ?>円</td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>

        <p>
            購入者：
            <span class="transaction-user">
                <span class="transaction-user-avatar">
                    <?= renderProfileAvatar(
                        $transaction,
                        '',
                        $transaction['buyer_name'] . 'のプロフィールアイコン'
                    ) ?>
                </span>
                <?= htmlspecialchars($transaction['buyer_name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </p>
    </section>

    <section>
        <h2>配送情報の入力</h2>

        <form action="shipping-register-process.php" method="post" class="shipping-form">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="transaction_id" value="<?= (int) $transaction_id ?>">

            <div class="form-group">
                <label for="carrier">配送業者</label><br>
                <select id="carrier" name="carrier" required>
                    <option value="">選択してください</option>
                    <?php foreach ($carriers as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="carrier_other">配送業者名（その他を選択した場合のみ入力）</label><br>
                <input
                    type="text"
                    id="carrier_other"
                    name="carrier_other"
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label for="tracking_number">追跡番号</label><br>
                <input
                    type="text"
                    id="tracking_number"
                    name="tracking_number"
                    maxlength="100"
                    required
                >
                <p class="hint">追跡番号の桁数は配送業者によって異なります。入力された値をそのまま保存します。</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-ship">発送完了</button>
                <a href="shipping-list.php">一覧に戻る</a>
            </div>

        </form>
    </section>

    <p>
        <a href="transaction-detail.php?transaction_id=<?= (int) $transaction_id ?>">取引詳細に戻る</a>
    </p>

</main>

<?php require '../../meetory-footer.php'; ?>
