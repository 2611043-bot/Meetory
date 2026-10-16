
<?php require_once '../../meetory-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログインしていなければログイン画面へ
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getPDO();

// ログイン中のユーザーが出品した商品だけ取得
$sql = 'SELECT goods_id, goods_name, price, listed_at
        FROM goods
        WHERE seller_id = ?
        ORDER BY listed_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$goods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../../meetory-header.php'; ?>

<main>
    <h1>自分の出品一覧</h1>

    <?php if (empty($goods_list)): ?>
        <p>まだ商品を出品していません。</p>
    <?php else: ?>
        <?php foreach ($goods_list as $goods): ?>
            <article>
                <h2>
                    <?= htmlspecialchars(
                        $goods['goods_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h2>

                <p>
                    価格：
                    <?= number_format((int)$goods['price']) ?>円
                </p>

                <p>
                    出品日時：
                    <?= htmlspecialchars(
                        $goods['listed_at'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <a href="goods-detail.php?goods_id=<?= (int)$goods['goods_id'] ?>">
                    商品詳細を見る
                </a>
            </article>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>

    <p><a href="goods-list.php">すべての商品を見る</a></p>
</main>

<?php require '../../meetory-footer.php'; ?>
