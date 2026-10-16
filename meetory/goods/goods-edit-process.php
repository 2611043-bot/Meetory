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

// 商品ID確認
if (
    !isset($_POST['goods_id']) ||
    !ctype_digit((string)$_POST['goods_id'])
) {
    exit('商品IDが正しくありません。');
}

$goods_id = (int)$_POST['goods_id'];
$user_id = $_SESSION['user_id'];

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

if (!is_string($condition) || !in_array($condition, $conditions, true)) {
    exit('商品の状態が正しくありません。');
}

$pdo = getPDO();

// カテゴリーが存在するか確認
$sql = 'SELECT COUNT(*) FROM categories WHERE category_id = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute([$category_id]);

if ((int)$stmt->fetchColumn() === 0) {
    exit('カテゴリーが存在しません。');
}

// 商品を更新（自分が出品した商品だけ）
$sql = 'UPDATE goods
        SET goods_name = ?,
            description = ?,
            price = ?,
            `condition` = ?,
            category_id = ?
        WHERE goods_id = ? AND seller_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $goods_name,
    $description,
    $price,
    $condition,
    $category_id,
    $goods_id,
    $user_id
]);

// 更新対象の商品が存在するか再確認
$sql = 'SELECT COUNT(*)
        FROM goods
        WHERE goods_id = ? AND seller_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id, $user_id]);

if ((int)$stmt->fetchColumn() === 0) {
    exit('商品が見つからないか、編集する権限がありません。');
}

// 商品詳細画面へ戻る
header('Location: goods-detail.php?goods_id=' . $goods_id);
exit;