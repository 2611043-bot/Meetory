<?php require_once '../../meetory-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 商品IDをURLから取得
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

// 商品IDが正しくない場合
if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

// データベースに接続
$pdo = getPDO();

// 指定された商品を取得
$sql = 'SELECT goods_id, seller_id, goods_name, description,
                price, `condition`, listed_at
        FROM goods
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

$goods = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が見つからない場合
if ($goods === false) {
    exit('商品が見つかりません。');
}
?>

<?php require '../../meetory-header.php'; ?>

<main>
    <h1>商品詳細</h1>

    <article>
        <h2>
            <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <p>
            価格：
            <?= number_format((int)$goods['price']) ?>円
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
    </article>

    <p>
        <a href="goods-list.php">商品一覧に戻る</a>
    </p>

<?php
if (
    isset($_SESSION['user_id']) &&
    (int)$_SESSION['user_id'] === (int)$goods['seller_id']
):
?>
    <p>
        <a href="goods-edit.php?goods_id=<?= (int)$goods['goods_id'] ?>">
            商品を編集する
        </a>
    </p>

    <p>
        <a href="goods-delete.php?goods_id=<?= (int)$goods['goods_id'] ?>">
            商品を削除する
        </a>
    </p>
<?php endif; ?>
</main>

<?php require '../../meetory-footer.php'; ?>
