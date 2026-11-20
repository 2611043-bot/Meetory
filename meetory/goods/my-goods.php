<?php require_once '../../meetory-functions.php';

// セッションを開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログインしていなければ表示させない
if (
    !isset($_SESSION['user_id']) ||
    !is_scalar($_SESSION['user_id']) ||
    !ctype_digit((string)$_SESSION['user_id']) ||
    (int)$_SESSION['user_id'] <= 0
) {
    exit('ログインしてください。');
}

// ログイン中のユーザーIDを取得
$user_id = (int)$_SESSION['user_id'];

// データベースに接続
$pdo = getPDO();

// ログイン中のユーザーが出品した商品と、各商品の先頭画像を取得
$sql = '
    SELECT
        g.goods_id,
        g.goods_name,
        g.description,
        g.price,
        g.`condition`,
        g.listed_at,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM goods AS g
    WHERE g.seller_id = ?
    ORDER BY g.listed_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

$goods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ページ固有CSS（キャッシュ自動更新付き）
$page_css = '/Meetory/meetory/assets/css/my-goods.css?v=' . filemtime(__DIR__ . '/../assets/css/my-goods.css');
?>

<link rel="stylesheet" href="<?= htmlspecialchars($page_css, ENT_QUOTES, 'UTF-8') ?>">

<?php require '../../meetory-header.php'; ?>

<div class="my-goods-page">
    <main class="my-goods-container">

        <!-- 出品履歴ヘッダーバー（タイトル＋出品件数＋新規出品ボタン） -->
        <div class="my-goods-header">
            <div class="my-goods-title-group">
                <h1>出品履歴</h1>
                <span class="my-goods-count">全 <?= count($goods_list) ?> 件</span>
            </div>
            <a href="goods-register.php" class="my-goods-register-btn">＋ 新しく出品する</a>
        </div>

        <?php if (count($goods_list) > 0): ?>

            <div class="my-goods-list">

                <?php foreach ($goods_list as $goods): ?>

                    <?php
                    // 商品画像のパスを取得・検証（goods-list.php と同じ安全チェック）
                    $image_path = $goods['image_path'] ?? '';

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

                    <article class="my-goods-item">
                        <!-- 履歴メタ情報（出品日・ステータス） -->
                        <div class="my-goods-meta">
                            <span class="my-goods-date">
                                出品日：<?= htmlspecialchars(date('Y/m/d', strtotime($goods['listed_at'])), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <span class="my-goods-status">出品中</span>
                        </div>

                        <!-- 商品画像 -->
                        <?php if ($image_path !== ''): ?>
                            <div class="my-goods-image">
                                <img
                                    src="/Meetory/<?= htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    loading="lazy"
                                >
                            </div>
                        <?php else: ?>
                            <p class="my-no-image-box">画像はありません</p>
                        <?php endif; ?>

                        <h2>
                            <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                        </h2>

                        <p class="my-goods-price">
                            価格：
                            <?= number_format((int)$goods['price']) ?>円
                        </p>

                        <p class="my-goods-condition">
                            商品の状態：
                            <?= htmlspecialchars($goods['condition'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="my-goods-description">
                            <?= nl2br(htmlspecialchars($goods['description'], ENT_QUOTES, 'UTF-8')) ?>
                        </p>

                        <a href="goods-detail.php?goods_id=<?= (int)$goods['goods_id'] ?>" class="my-goods-detail-link">
                            商品詳細を見る
                        </a>
                    </article>

                    <hr>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p class="my-goods-empty">まだ商品を出品していません。</p>

        <?php endif; ?>

        <p class="my-goods-all-link">
            <a href="goods-list.php">すべての商品を見る</a>
        </p>
    </main>
</div>

<?php require '../../meetory-footer.php'; ?>