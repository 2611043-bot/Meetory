<?php require_once '../../meetory-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// CSRFトークンを生成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 商品IDを取得
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

$pdo = getPDO();


$is_admin = isset($_SESSION['role'])
    && $_SESSION['role'] === 'admin';

// 商品情報を取得
$sql = 'SELECT goods_id, seller_id, goods_name, price
        FROM goods
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

$goods = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が存在するか確認
if (!$goods) {
    exit('商品が見つかりません。');
}

// 管理者でなく、出品者本人でもない場合は拒否
if (
    !$is_admin &&
    (string)$goods['seller_id'] !== (string)$_SESSION['user_id']
) {
    exit('この商品を削除する権限がありません。');
}


require '../../meetory-header.php';
?>

<main>
    <h1>商品削除の確認</h1>

    <p>次の商品を削除してもよろしいですか？</p>

    <h2>
        <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
    </h2>

    <p>
        価格：<?= number_format((int)$goods['price']) ?>円
    </p>

    <p>削除すると元に戻せません。</p>

    <form action="goods-delete-process.php" method="post">

        <input
            type="hidden"
            name="goods_id"
            value="<?= (int)$goods['goods_id'] ?>"
        >

        <!-- CSRFトークン -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['csrf_token'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <button type="submit">削除する</button>

    </form>

    <p>
        <a href="goods-detail.php?goods_id=<?= (int)$goods['goods_id'] ?>">
            キャンセル
        </a>
    </p>
</main>

<?php require '../../meetory-footer.php'; ?>