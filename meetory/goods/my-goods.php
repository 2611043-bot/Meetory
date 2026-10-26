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
$user_id = $_SESSION['user_id'];

// データベースに接続
$pdo = getPDO();

// ログイン中のユーザーが出品した商品だけを取得
$sql = 'SELECT goods_id, goods_name, description, price, `condition`, listed_at
        FROM goods
        WHERE seller_id = ?
        ORDER BY listed_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

$goods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../../meetory-header.php'; ?>

<main>
    <h1>自分の出品商品</h1>

    <?php if (count($goods_list) > 0): ?>

        <?php foreach ($goods_list as $goods): ?>

            <article>
                <h2>
                    <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <p>
                    価格：
                    <?= number_format((int)$goods['price']) ?>円
                </p>

                <p>
                    商品の状態：
                    <?= htmlspecialchars($goods['condition'], ENT_QUOTES, 'UTF-8') ?>
                </p>

                <p>
                    <?= nl2br(htmlspecialchars($goods['description'], ENT_QUOTES, 'UTF-8')) ?>
                </p>

                <a href="goods-detail.php?goods_id=<?= (int)$goods['goods_id'] ?>">
                    商品詳細を見る
                </a>
            </article>

            <hr>

        <?php endforeach; ?>

    <?php else: ?>

        <p>まだ商品を出品していません。</p>

    <?php endif; ?>

    <p>
        <a href="goods-list.php">すべての商品を見る</a>
    </p>
</main>

<?php require '../../meetory-footer.php'; ?>
