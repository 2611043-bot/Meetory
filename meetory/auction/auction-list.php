<?php

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// 開催中のオークションを取得
$sql = '
    SELECT
        g.goods_id,
        g.goods_name,
        g.price,
        (
            SELECT gi.image_path
            FROM goods_images gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path,
        a.end_at
    FROM goods AS g
    INNER JOIN auctions AS a
        ON a.goods_id = g.goods_id
    WHERE a.status = :status
      AND a.start_at <= NOW()
      AND a.end_at > NOW()
      AND g.is_sold = 0
    ORDER BY g.listed_at DESC, g.goods_id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':status' => 'active',
]);
$auction_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../../meetory-header.php'; ?>

<!-- ページ固有のスタイルシートを読み込む（オークション一覧専用） -->
<link rel="stylesheet" href="../assets/css/auction-list.css">

<main>
    <h1>開催中のオークション</h1>

    <?php if (empty($auction_list)): ?>

        <p>現在開催中のオークションはありません。</p>

    <?php else: ?>

        <div class="goods-grid">
            <?php foreach ($auction_list as $auction): ?>

                <a
                    class="goods-card"
                    href="auction-detail.php?goods_id=<?= (int) $auction['goods_id'] ?>"
                >
                    <?php
                    $image_path = $auction['image_path'] ?? '';

                    $is_valid_image = is_string($image_path)
                        && preg_match(
                            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
                            $image_path
                        );
                    ?>

                    <?php if ($is_valid_image): ?>
                        <img
                            src="/Meetory/<?= htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($auction['goods_name'], ENT_QUOTES, 'UTF-8') ?>"
                            width="300"
                            height="200"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <div class="no-image">画像なし</div>
                    <?php endif; ?>

                    <h2>
                        <?= htmlspecialchars($auction['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <p class="goods-price">
                        現在価格：
                        <?= number_format((int) $auction['price']) ?>円
                    </p>

                    <p class="auction-end">
                        終了日時：
                        <?= htmlspecialchars($auction['end_at'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </a>

            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <p>
        <a href="../goods/goods-list.php">通常商品一覧に戻る</a>
    </p>
</main>

<?php require '../../meetory-footer.php'; ?>
