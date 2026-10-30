<?php
// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 共通関数を読み込む
require_once __DIR__ . '/../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// 新着商品を6件取得
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
    ORDER BY g.listed_at DESC, g.goods_id DESC
    LIMIT 6
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$goods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- CSSを読み込む -->
<link rel="stylesheet" href="/meetory_php/meetory/assets/css/home.css">

<?php require_once __DIR__ . '/../meetory-header.php'; ?>

<main class="home-main">
    <section class="home-intro">
        <h1>Meetoryへようこそ！</h1>
        <p>あなたのお気に入りの商品を見つけよう。</p>
        <a href="/meetory_php/meetory/goods/goods-list.php">
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
                        href="/meetory_php/meetory/goods/goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>"
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
                                src="/meetory_php/<?= htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') ?>"
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
            <a href="/meetory_php/meetory/goods/goods-list.php">
                もっと見る
            </a>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../meetory-footer.php'; ?>

<!-- JavaScriptを読み込む -->
<script src="/meetory_php/meetory/assets/js/home.js" defer></script>