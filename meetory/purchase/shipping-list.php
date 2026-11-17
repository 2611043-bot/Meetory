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

// CSRFトークンを作成（ヘッダー共通トークンと併用）
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// このユーザーが出品者である取引を取得（発送待ち・発送済み）
$sql = '
    SELECT DISTINCT
        b.transaction_id,
        b.transaction_status,
        b.purchased_at,
        b.buyer_id,
        buyer.user_name AS buyer_name
    FROM buy AS b
    INNER JOIN users AS buyer
        ON b.buyer_id = buyer.user_id
    INNER JOIN buy_items AS bi
        ON bi.transaction_id = b.transaction_id
    INNER JOIN goods AS g
        ON g.goods_id = bi.goods_id
    WHERE g.seller_id = :seller_id
      AND b.transaction_status IN (:purchased, :shipped)
    ORDER BY b.purchased_at DESC, b.transaction_id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'seller_id' => $user_id,
    'purchased' => '購入済み',
    'shipped' => '発送済み'
]);

$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 各取引の出品者分の商品と配送情報を取得
$transactionData = [];
foreach ($transactions as $transaction) {
    $tid = (int) $transaction['transaction_id'];

    // この出品者の商品
    $stmtGoods = $pdo->prepare('
        SELECT
            g.goods_id,
            g.goods_name,
            bi.purchase_price
        FROM buy_items AS bi
        INNER JOIN goods AS g
            ON bi.goods_id = g.goods_id
        WHERE bi.transaction_id = :transaction_id
          AND g.seller_id = :seller_id
        ORDER BY bi.buy_item_id ASC
    ');
    $stmtGoods->execute([
        'transaction_id' => $tid,
        'seller_id' => $user_id
    ]);
    $goods = $stmtGoods->fetchAll(PDO::FETCH_ASSOC);

    // 配送情報（この出品者分）
    $stmtDelivery = $pdo->prepare(
        'SELECT * FROM deliveries WHERE transaction_id = :transaction_id AND seller_id = :seller_id'
    );
    $stmtDelivery->execute([
        'transaction_id' => $tid,
        'seller_id' => $user_id
    ]);
    $delivery = $stmtDelivery->fetch(PDO::FETCH_ASSOC);

    $transactionData[] = [
        'transaction' => $transaction,
        'goods' => $goods,
        'delivery' => $delivery
    ];
}

?>

<?php require '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/shipping.css">

<main>

    <h1>発送管理一覧</h1>
    <p>あなたが出品した商品が含まれる取引の発送状況を確認できます。</p>

    <p>
        <a href="../user/mypage.php">マイページに戻る</a>
    </p>

    <?php if (empty($transactionData)): ?>

        <p>発送対象の取引はありません。</p>

    <?php else: ?>

        <section>
            <h2>発送待ち</h2>

            <?php $waiting = array_filter($transactionData, function ($data) {
                return $data['transaction']['transaction_status'] === '購入済み';
            }); ?>

            <?php if (empty($waiting)): ?>

                <p>発送待ちの取引はありません。</p>

            <?php else: ?>

                <table class="shipping-list-table">
                    <thead>
                        <tr>
                            <th>取引ID</th>
                            <th>商品名</th>
                            <th>購入者</th>
                            <th>購入日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($waiting as $data): ?>

                            <tr>
                                <td><?= (int) $data['transaction']['transaction_id'] ?></td>
                                <td>
                                    <?php foreach ($data['goods'] as $i => $g): ?>
                                        <?= htmlspecialchars($g['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($i < count($data['goods']) - 1): ?><br><?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <span class="transaction-user">
                                        <span class="transaction-user-avatar">
                                            <?= renderProfileAvatar(
                                                $data['transaction'],
                                                '',
                                                $data['transaction']['buyer_name'] . 'のプロフィールアイコン'
                                            ) ?>
                                        </span>
                                        <?= htmlspecialchars($data['transaction']['buyer_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars(
                                        date('Y年m月d日 H:i:s', strtotime($data['transaction']['purchased_at'])),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>
                                <td>
                                    <a
                                        href="shipping-register.php?transaction_id=<?= (int) $data['transaction']['transaction_id'] ?>"
                                        class="btn-ship"
                                    >
                                        発送手続きをする
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            <?php endif; ?>
        </section>

        <section>
            <h2>発送済み</h2>

            <?php $shipped = array_filter($transactionData, function ($data) {
                return $data['transaction']['transaction_status'] === '発送済み';
            }); ?>

            <?php if (empty($shipped)): ?>

                <p>発送済みの取引はありません。</p>

            <?php else: ?>

                <table class="shipping-list-table">
                    <thead>
                        <tr>
                            <th>取引ID</th>
                            <th>商品名</th>
                            <th>購入者</th>
                            <th>配送業者</th>
                            <th>追跡番号</th>
                            <th>発送日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($shipped as $data): ?>

                            <tr>
                                <td><?= (int) $data['transaction']['transaction_id'] ?></td>
                                <td>
                                    <?php foreach ($data['goods'] as $i => $g): ?>
                                        <?= htmlspecialchars($g['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($i < count($data['goods']) - 1): ?><br><?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= htmlspecialchars($data['transaction']['buyer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($data['delivery']): ?>
                                        <?= htmlspecialchars($data['delivery']['carrier'] === 'その他'
                                            ? ($data['delivery']['carrier_other'] ?? 'その他')
                                            : $data['delivery']['carrier'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($data['delivery']): ?>
                                        <?= htmlspecialchars($data['delivery']['tracking_number'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($data['delivery'] && $data['delivery']['shipped_at'] !== null): ?>
                                        <?= htmlspecialchars(
                                            date('Y年m月d日 H:i:s', strtotime($data['delivery']['shipped_at'])),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a
                                        href="shipping-detail.php?transaction_id=<?= (int) $data['transaction']['transaction_id'] ?>"
                                    >
                                        配送情報を確認
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            <?php endif; ?>
        </section>

    <?php endif; ?>

</main>

<?php require '../../meetory-footer.php'; ?>
