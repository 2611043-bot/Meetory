<?php

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// 商品IDをURLから取得
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

// 商品IDが正しくない場合
if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

// 商品情報を取得
$sql = 'SELECT
            goods_id,
            seller_id,
            goods_name,
            description,
            price,
            `condition`,
            listed_at,
            is_sold
        FROM goods
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

$goods = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が見つからない場合
if ($goods === false) {
    exit('商品が見つかりません。');
}

// 商品画像を取得
$image_sql = '
    SELECT image_path
    FROM goods_images
    WHERE goods_id = ?
    ORDER BY display_order ASC, image_id ASC
';

$image_stmt = $pdo->prepare($image_sql);
$image_stmt->execute([$goods_id]);

$goods_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

// 商品画像のパスを検証
foreach ($goods_images as &$image) {
    $image_path = $image['image_path'] ?? '';

    if (
        !is_string($image_path) ||
        !preg_match(
            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
            $image_path
        )
    ) {
        $image['image_path'] = '';
    }
}
unset($image);

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$csrf_token = $_SESSION['csrf_token'];

// ログイン中のユーザーが出品者か確認
$is_owner = isset($_SESSION['user_id'])
    && (int) $_SESSION['user_id'] === (int) $goods['seller_id'];

// 管理者か確認
$is_admin = isset($_SESSION['role'])
    && $_SESSION['role'] === 'admin';

// カートに入れられる条件
$can_add_cart = isset($_SESSION['user_id'])
    && !$is_owner
    && (int) $goods['is_sold'] === 0;

?>

<?php require '../../meetory-header.php'; ?>

<main>

    <h1>商品詳細</h1>

    <article>

<!-- 商品画像 -->
<div class="goods-images">

    <?php if (!empty($goods_images)): ?>

        <?php
        $has_valid_image = false;
        ?>

        <?php foreach ($goods_images as $image): ?>

            <?php if ($image['image_path'] !== ''): ?>

                <?php $has_valid_image = true; ?>

                <img
                    src="/meetory_php/<?= htmlspecialchars(
                        $image['image_path'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    alt="<?= htmlspecialchars(
                        $goods['goods_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    width="300"
                    loading="lazy"
                >

            <?php endif; ?>

        <?php endforeach; ?>

        <?php if (!$has_valid_image): ?>

            <p>画像はありません</p>

        <?php endif; ?>

    <?php else: ?>

        <p>画像はありません</p>

    <?php endif; ?>

</div>

        <h2>
            <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <p>
            価格：
            <?= number_format((int) $goods['price']) ?>円
        </p>

        <h3>商品説明</h3>

        <p>
            <?= nl2br(htmlspecialchars($goods['description'], ENT_QUOTES, 'UTF-8')) ?>
        </p>

        <p>
            商品の状態：
            <?= htmlspecialchars($goods['condition'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            出品日時：
            <?= htmlspecialchars($goods['listed_at'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if ((int) $goods['is_sold'] === 1): ?>

            <p>この商品は売却済みです。</p>

        <?php endif; ?>

    </article>

    <hr>

    <!-- 出品者にDMを送る -->
    <?php if (
        isset($_SESSION['user_id']) &&
        (int) $_SESSION['user_id'] !== (int) $goods['seller_id']
    ): ?>

        <h2>出品者へのお問い合わせ</h2>

        <p>
            <a href="../messages/message-start.php?goods_id=<?= (int) $goods['goods_id'] ?>">
                出品者にDMを送る
            </a>
        </p>

    <?php endif; ?>

    <hr>

    <h2>購入・カート</h2>

    <?php if ($can_add_cart): ?>

        <form action="../cart/cart-add.php" method="post">

            <input
                type="hidden"
                name="goods_id"
                value="<?= (int) $goods['goods_id'] ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
            >

            <button type="submit">カートに入れる</button>

        </form>

        <p>
            <a href="../purchase/buy.php?goods_id=<?= (int) $goods['goods_id'] ?>">
                この商品を購入する
            </a>
        </p>

    <?php elseif (!isset($_SESSION['user_id'])): ?>

        <p>カートの利用・購入にはログインしてください。</p>

        <p>
            <a href="../user/login.php">ログイン</a>
        </p>

    <?php elseif ($is_owner): ?>

        <p>自分が出品した商品は購入できません。</p>

    <?php else: ?>

        <p>この商品はカートに追加できません。</p>

    <?php endif; ?>

    <hr>

    <h2>その他</h2>

    <p>
        <a href="goods-list.php">商品一覧に戻る</a>
    </p>

    <?php if ($is_admin || $is_owner): ?>

        <p>
            <a href="goods-edit.php?goods_id=<?= (int) $goods['goods_id'] ?>">
                商品を編集する
            </a>
        </p>

        <p>
            <a href="goods-delete.php?goods_id=<?= (int) $goods['goods_id'] ?>">
                商品を削除する
            </a>
        </p>

    <?php endif; ?>

</main>

<?php require '../../meetory-footer.php'; ?>