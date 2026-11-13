<?php
// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 共通関数を読み込む
require_once __DIR__ . '/../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// 新着商品を12件取得（オークション商品は除外）
$sql = "
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
        ) AS image_path
    FROM goods g
    WHERE g.is_sold = 0
      AND NOT EXISTS (
          SELECT 1 FROM auctions a WHERE a.goods_id = g.goods_id
      )
    ORDER BY g.listed_at DESC, g.goods_id DESC
    LIMIT 12
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$goods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 開催中のオークションを最大6件取得
$auction_sql = "
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
    FROM goods g
    INNER JOIN auctions a ON a.goods_id = g.goods_id
    WHERE a.status = 'active'
      AND a.start_at <= NOW()
      AND a.end_at > NOW()
      AND g.is_sold = 0
    ORDER BY g.listed_at DESC, g.goods_id DESC
    LIMIT 6
";

$auction_stmt = $pdo->prepare($auction_sql);
$auction_stmt->execute();
$auction_list = $auction_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- CSSを読み込む -->
<link rel="stylesheet" href="/Meetory/meetory/assets/css/home.css">

<?php require_once __DIR__ . '/../meetory-header.php'; ?>

<main class="home-main">
    <section class="home-intro">
        <h1>Meetoryへようこそ！</h1>
        <p>あなたのお気に入りの商品を見つけよう。</p>
        <a href="/Meetory/meetory/goods/goods-list.php">
            商品を探す
        </a>
    </section>

    <section class="new-goods">
        <h2>新着商品</h2>

        <?php if (empty($goods_list)): ?>
            <p>現在、出品されている商品はありません。</p>
        <?php else: ?>
            <div class="goods-grid">
                <?php foreach ($goods_list as $goods): ?>
                    <a
                        class="goods-card"
                        href="/Meetory/meetory/goods/goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>"
                    >
                        <?php
                        $image_path = $goods['image_path'] ?? '';

                        $is_valid_image = is_string($image_path)
                            && preg_match(
                                '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
                                $image_path
                            );
                        ?>

                        <?php if ($is_valid_image): ?>
                            <img
                                src="/Meetory/<?= htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                        <?php else: ?>
                            <div class="no-image">画像なし</div>
                        <?php endif; ?>

                        <h3>
                            <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p class="goods-price">
                            ¥<?= number_format((int) $goods['price']) ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="more-goods">
            <a href="/Meetory/meetory/goods/goods-list.php">
                もっと見る（通常商品）
            </a>
        </div>
    </section>

    <section class="active-auctions">
        <h2>開催中のオークション</h2>

        <?php if (empty($auction_list)): ?>
            <p>現在開催中のオークションはありません。</p>
        <?php else: ?>
            <div class="goods-grid">
                <?php foreach ($auction_list as $auction): ?>
                    <a
                        class="goods-card"
                        href="/Meetory/meetory/auction/auction-detail.php?goods_id=<?= (int) $auction['goods_id'] ?>"
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
                            >
                        <?php else: ?>
                            <div class="no-image">画像なし</div>
                        <?php endif; ?>

                        <h3>
                            <?= htmlspecialchars($auction['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p class="goods-price">
                            現在価格：¥<?= number_format((int) $auction['price']) ?>
                        </p>

                        <p class="auction-end">
                            終了日時：<?= htmlspecialchars($auction['end_at'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="more-goods">
            <a href="/Meetory/meetory/auction/auction-list.php">
                もっと見る（オークション）
            </a>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../meetory-footer.php'; ?>

<!-- JavaScriptを読み込む -->
<script src="/Meetory/meetory/assets/js/home.js" defer></script>
