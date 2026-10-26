
<?php
session_start();

require '../../meetory-functions.php';

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// POST送信確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。商品編集画面を開き直してください。');
}

// 商品ID確認
$goods_id_input = $_POST['goods_id'] ?? '';

if (
    !is_string($goods_id_input) ||
    !ctype_digit($goods_id_input) ||
    (int)$goods_id_input < 1
) {
    exit('商品IDが正しくありません。');
}

$goods_id = (int)$goods_id_input;
$user_id = $_SESSION['user_id'];

// 管理者かどうか確認
$is_admin = isset($_SESSION['role'])
    && $_SESSION['role'] === 'admin';

$goods_name = trim($_POST['goods_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price_input = $_POST['price'] ?? '';
$condition = $_POST['condition'] ?? '';
$category_id_input = $_POST['category_id'] ?? '';

// 入力チェック
if ($goods_name === '' || $description === '') {
    exit('商品名と商品説明を入力してください。');
}

if (
    !is_string($price_input) ||
    !ctype_digit($price_input)
) {
    exit('価格は0以上の整数で入力してください。');
}

$price = (int)$price_input;

if ($price > 2147483647) {
    exit('価格が大きすぎます。');
}

if (
    !is_string($category_id_input) ||
    !ctype_digit($category_id_input) ||
    (int)$category_id_input < 1
) {
    exit('カテゴリーが正しくありません。');
}

$category_id = (int)$category_id_input;

$conditions = [
    '新品',
    '未使用',
    '目立った傷や汚れなし',
    'やや傷や汚れあり',
    '傷や汚れあり'
];

if (
    !is_string($condition) ||
    !in_array($condition, $conditions, true)
) {
    exit('商品の状態が正しくありません。');
}

$pdo = getPDO();

// 商品の出品者を確認
$sql = 'SELECT seller_id
        FROM goods
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が存在するか確認
if (!$product) {
    exit('商品が見つかりません。');
}

// 管理者でなく、出品者本人でもない場合は拒否
if (
    !$is_admin &&
    (string)$product['seller_id'] !== (string)$user_id
) {
    exit('この商品を編集する権限がありません。');
}

// カテゴリーが存在するか確認
$sql = 'SELECT COUNT(*)
        FROM categories
        WHERE category_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$category_id]);

if ((int)$stmt->fetchColumn() === 0) {
    exit('カテゴリーが存在しません。');
}

// 商品を更新
$sql = 'UPDATE goods
        SET goods_name = ?,
            description = ?,
            price = ?,
            `condition` = ?,
            category_id = ?
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $goods_name,
    $description,
    $price,
    $condition,
    $category_id,
    $goods_id
]);

// 商品詳細画面へ戻る
header('Location: goods-detail.php?goods_id=' . $goods_id);
exit;
?>
