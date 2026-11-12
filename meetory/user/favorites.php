
<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログインしているか確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// DB接続用の共通ファイルを読み込む
require_once '../../meetory-functions.php';

// ログイン中のユーザーIDを取得
$user_id = (int) $_SESSION['user_id'];

// DB接続
$pdo = getPDO();

// ほしいものリストを取得
$sql = '
    SELECT
        f.favorite_id,
        f.created_at AS favorited_at,
        g.goods_id,
        g.goods_name,
        g.description,
        g.price,
        g.`condition`,
        g.listed_at,
        g.is_sold,
        g.seller_id,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM favorites AS f
    INNER JOIN goods AS g
        ON f.goods_id = g.goods_id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC, f.favorite_id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 商品画像のパスを検証
foreach ($favorites as &$favorite) {
    $image_path = $favorite['image_path'] ?? '';

    if (
        !is_string($image_path) ||
        !preg_match(
            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
            $image_path
        )
    ) {
        $favorite['image_path'] = '';
    }
}
unset($favorite);

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// 共通ヘッダー
require '../../meetory-header.php';

?>

<!-- ページ固有のスタイルシートを読み込む（ほしいものリスト専用） -->
<link rel="stylesheet" href="../assets/css/favorites.css">

<main>

    <h1>ほしいものリスト</h1>

    <?php if (empty($favorites)): ?>

        <p>ほしいものリストはまだ空です。</p>

        <p>
            <a href="../index.php">ホームに戻る</a>
        </p>

    <?php else: ?>

        <p>
            登録件数：
            <?= htmlspecialchars((string) count($favorites), ENT_QUOTES, 'UTF-8') ?> 件
        </p>

        <div class="favorites-list">

        <?php foreach ($favorites as $favorite): ?>

            <article class="favorite-item">

                <form action="../goods/goods-favorite-delete.php" method="post" class="favorite-delete-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="goods_id" value="<?= (int) $favorite['goods_id'] ?>">
                    <input type="hidden" name="return_url" value="/Meetory/meetory/user/favorites.php">
                    <button type="submit" class="favorite-delete-btn" onclick="return confirm('ほしいものリストから削除しますか？');">削除</button>
                </form>

                <!-- 商品画像 -->
                <?php if (!empty($favorite['image_path'])): ?>
                    <div class="favorite-image">
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $favorite['goods_id'] ?>">
                            <img
                                src="/Meetory/<?= htmlspecialchars($favorite['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($favorite['goods_name'], ENT_QUOTES, 'UTF-8') ?>"
                                width="200"
                                loading="lazy"
                            >
                        </a>
                    </div>
                <?php else: ?>
                    <div class="favorite-image no-image">
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $favorite['goods_id'] ?>">
                            画像はありません
                        </a>
                    </div>
                <?php endif; ?>

                <div class="favorite-info">
                    <h2>
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $favorite['goods_id'] ?>">
                            <?= htmlspecialchars($favorite['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </h2>

                    <p>
                        価格：
                        <?= number_format((int) $favorite['price']) ?>円
                    </p>

                    <p>
                        商品の状態：
                        <?= htmlspecialchars($favorite['condition'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <p>
                        出品日時：
                        <?= htmlspecialchars(date('Y年m月d日', strtotime($favorite['listed_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <p>
                        登録日時：
                        <?= htmlspecialchars(date('Y年m月d日H:i:s', strtotime($favorite['favorited_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <?php if ((int) $favorite['is_sold'] === 1): ?>
                        <p class="favorite-sold">売却済み</p>
                    <?php else: ?>
                        <p class="favorite-active">出品中</p>
                    <?php endif; ?>

                    <p>
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $favorite['goods_id'] ?>">商品詳細を見る</a>
                    </p>
                </div>

                <hr>

            </article>

        <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <p>
        <a href="mypage.php">マイページに戻る</a>
    </p>

</main>

<?php require '../../meetory-footer.php'; ?>
