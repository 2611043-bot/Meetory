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

// 配送ステータスのラベル
$deliveryStatusLabels = [
    'awaiting_shipment' => '発送待ち',
    'shipped' => '発送済み',
    'received' => '受取確認済み'
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

// 取引情報と購入者情報を取得
$stmt = $pdo->prepare(
    'SELECT
        b.transaction_id,
        b.buyer_id,
        b.transaction_status,
        b.purchased_at,
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

// 取引に含まれる商品と出品者を取得
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

// ログイン中のユーザーが購入者または出品者か確認
$is_buyer = (int) $transaction['buyer_id'] === $user_id;
$is_seller = false;
$seller_ids = [];
foreach ($items as $item) {
    $sid = (int) $item['seller_id'];
    $seller_ids[$sid] = true;
    if ($sid === $user_id) {
        $is_seller = true;
    }
}

if (!$is_buyer && !$is_seller) {
    exit('この取引を確認する権限がありません。');
}

// 配送情報を取得（購入者は全て、出品者は自分の分のみ）
$stmt = $pdo->prepare(
    'SELECT
        d.*,
        s.user_name AS seller_name
    FROM deliveries AS d
    INNER JOIN users AS s
        ON s.user_id = d.seller_id
    WHERE d.transaction_id = :transaction_id'
    . ($is_seller && !$is_buyer ? ' AND d.seller_id = :seller_id' : '')
);

if ($is_seller && !$is_buyer) {
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'seller_id' => $user_id
    ]);
} else {
    $stmt->execute(['transaction_id' => $transaction_id]);
}

$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 成功・エラーメッセージ
$message = $_GET['message'] ?? null;
$error = $_GET['error'] ?? null;

?>

<?php require '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/shipping.css">
<link rel="stylesheet" href="../assets/css/transaction-detail.css">

<main>

    <h1>配送情報</h1>

    <?php if ($message): ?>
        <p class="msg success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p>
        取引ID：
        <?= (int) $transaction['transaction_id'] ?>
    </p>

    <p>
        取引状況：
        <?= htmlspecialchars($transaction['transaction_status'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        購入日時：
        <?= htmlspecialchars(
            date('Y年m月d日 H:i:s', strtotime($transaction['purchased_at'])),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

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

    <section>
        <h2>商品情報</h2>

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
    </section>

    <section>
        <h2>配送状況</h2>

        <?php if (empty($deliveries)): ?>

            <p>配送情報はまだ登録されていません。</p>

        <?php else: ?>

            <?php foreach ($deliveries as $delivery): ?>

                <div class="shipping-card">
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
                        <?php if ($delivery['shipped_at'] !== null): ?>
                            <?= htmlspecialchars(
                                date('Y年m月d日 H:i:s', strtotime($delivery['shipped_at'])),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </p>

                    <p>
                        配送ステータス：
                        <span class="status-badge">
                            <?= htmlspecialchars(
                                $deliveryStatusLabels[$delivery['delivery_status']] ?? $delivery['delivery_status'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>
                    </p>

                    <?php if (
                        $is_buyer &&
                        $delivery['delivery_status'] === 'shipped' &&
                        $transaction['transaction_status'] !== '取引完了' &&
                        empty($transaction['cancelled_at'])
                    ): ?>

                        <form action="receive-confirm.php" method="get" class="receive-link-form">
                            <input type="hidden" name="transaction_id" value="<?= (int) $transaction_id ?>">
                            <input type="hidden" name="seller_id" value="<?= (int) $delivery['seller_id'] ?>">
                            <button type="submit" class="btn-receive">受取確認をする</button>
                        </form>

                    <?php endif; ?>

                    <?php if ($delivery['delivery_status'] === 'received' && $delivery['received_at'] !== null): ?>

                        <p class="received-note">
                            受取確認日時：
                            <?= htmlspecialchars(
                                date('Y年m月d日 H:i:s', strtotime($delivery['received_at'])),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                    <?php endif; ?>

                    <hr>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>
    </section>

    <p>
        <a href="transaction-detail.php?transaction_id=<?= (int) $transaction_id ?>">取引詳細に戻る</a>
    </p>

    <?php if ($is_buyer): ?>
        <p>
            <a href="shipping-list.php">発送管理一覧に戻る</a>
        </p>
    <?php endif; ?>

</main>

<?php require '../../meetory-footer.php'; ?>
