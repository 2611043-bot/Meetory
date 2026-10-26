<?php
// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 共通処理・DB接続を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../user/login.php');
    exit;
}

// URLから商品IDを取得
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

if (!$goods_id || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

// 商品情報と出品者名を取得
$stmt = $pdo->prepare(
    'SELECT
        g.goods_id,
        g.goods_name,
        g.description,
        g.price,
        g.seller_id,
        g.is_sold,
        u.user_name AS seller_name
    FROM goods AS g
    INNER JOIN users AS u
        ON g.seller_id = u.user_id
    WHERE g.goods_id = :goods_id'
);

$stmt->execute(['goods_id' => $goods_id]);
$goods = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が存在しない場合
if (!$goods) {
    exit('商品が見つかりません。');
}

// 自分が出品した商品は購入できない
if ((int) $_SESSION['user_id'] === (int) $goods['seller_id']) {
    exit('自分が出品した商品は購入できません。');
}

// CSRF対策用トークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
?>

<?php require_once '../../meetory-header.php'; ?>

<h1>購入内容の確認</h1>

<h2><?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?></h2>

<p>
    商品説明：
    <?= nl2br(htmlspecialchars($goods['description'], ENT_QUOTES, 'UTF-8')) ?>
</p>

<p>
    価格：
    <?= number_format((int) $goods['price']) ?>円
</p>

<p>
    出品者：
    <?= htmlspecialchars($goods['seller_name'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p>上記の商品を購入しますか？</p>

<form action="buy-process.php" method="post">
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

    <h3>配送先住所</h3>

    <label for="address">住所を入力してください</label><br>
    <input
        type="text"
        id="address"
        name="address"
        maxlength="255"
        required
    >

    <h3>支払い方法</h3>

    <label for="payment_method">支払い方法を選択してください</label><br>

    <select id="payment_method" name="payment_method" required>
        <option value="">選択してください</option>
        <option value="銀行振込（テスト）">銀行振込（テスト）</option>
        <option value="その他（テスト）">その他（テスト）</option>
    </select>

    <p>※現在はテスト用のため、実際の決済や送金は行いません。</p>

    <button type="submit">購入を確定する</button>
</form>

<p>
    <a href="../goods/goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>">
        商品詳細に戻る
    </a>
</p>

<?php require_once '../../meetory-footer.php'; ?>
