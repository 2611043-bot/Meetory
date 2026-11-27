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

// ログイン中のユーザーID
$user_id = (int) $_SESSION['user_id'];

// 取引中の取引と商品を取得（購入者または出品者）
$sql = '
    SELECT
        b.transaction_id,
        b.transaction_status,
        b.purchased_at,
        bi.buy_item_id,
        bi.purchase_price,
        g.goods_id,
        g.goods_name,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM buy AS b
    INNER JOIN buy_items AS bi
        ON b.transaction_id = bi.transaction_id
    INNER JOIN goods AS g
        ON g.goods_id = bi.goods_id
    WHERE (b.buyer_id = :buyer_id OR g.seller_id = :seller_id)
      AND b.transaction_status IN (:purchased, :shipped)
    ORDER BY b.purchased_at DESC, b.transaction_id DESC, bi.buy_item_id ASC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'buyer_id' => $user_id,
    'seller_id' => $user_id,
    'purchased' => '購入済み',
    'shipped' => '発送済み'
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/transaction-list.css?v=<?= filemtime(__DIR__ . '/../assets/css/transaction-list.css') ?>">

<div class="transaction-list-page">
    <main class="transaction-list-container">
        <h1>取引中の商品一覧</h1>

        <?php if (empty($rows)): ?>

            <p class="transaction-list-empty">取引中の商品はありません。</p>

        <?php else: ?>

            <table class="transaction-list-table">
                <thead>
                    <tr>
                        <th>商品名</th>
                        <th>購入価格</th>
                        <th>取引状態</th>
                        <th>購入日時</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($rows as $row): ?>

                        <?php
                        $image_path = $row['image_path'] ?? '';
                        if (
                            !is_string($image_path) ||
                            !preg_match(
                                '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
                                $image_path
                            ) ||
                            !is_file(__DIR__ . '/../../' . $image_path)
                        ) {
                            $image_path = '';
                        }
                        ?>

                        <tr>
                            <td class="transaction-list-product" data-label="商品名">
                                <span class="transaction-list-product-image<?= $image_path === '' ? ' is-missing' : '' ?>" aria-hidden="true">
                                    <?php if ($image_path !== ''): ?>
                                        <img
                                            src="/Meetory/<?= htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') ?>"
                                            alt=""
                                            loading="lazy"
                                            onerror="this.parentElement.classList.add('is-missing')"
                                        >
                                    <?php endif; ?>
                                    <span class="transaction-list-no-image">
                                        <i class="fa-regular fa-image" aria-hidden="true"></i>
                                        <span>画像なし</span>
                                    </span>
                                </span>
                                <span class="transaction-list-product-name">
                                    <?= htmlspecialchars($row['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td data-label="購入価格">
                                <span class="transaction-list-price">
                                    <?= number_format((int) $row['purchase_price']) ?>円
                                </span>
                            </td>
                            <td data-label="取引状態">
                                <span class="transaction-status-badge <?= $row['transaction_status'] === '発送済み' ? 'is-shipped' : 'is-purchased' ?>">
                                    <?= htmlspecialchars($row['transaction_status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td data-label="購入日時">
                                <?= htmlspecialchars(
                                    date('Y年m月d日 H:i:s', strtotime($row['purchased_at'])),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                            <td class="transaction-list-actions" data-label="操作">
                                <a
                                    class="transaction-detail-link"
                                    href="transaction-detail.php?transaction_id=<?= (int) $row['transaction_id'] ?>"
                                >
                                    取引詳細を見る
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>

        <p class="transaction-list-back">
            <a href="../user/mypage.php">マイページに戻る</a>
        </p>

    </main>
</div>

<?php require '../../meetory-footer.php'; ?>
