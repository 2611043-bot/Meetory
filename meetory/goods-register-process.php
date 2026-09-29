<?php

// セッション開始
session_start();

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require_once '../meetory-functions.php';

// DB接続
$pdo = getPDO();

// 出品フォームから送信された情報を受け取る
$goods_name = $_POST['goods_name'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? '';
$goods_condition = $_POST['goods_condition'] ?? '';
$category_id = $_POST['category_id'] ?? '';

// 受け取った情報を確認する
echo <<<HTML
<h2>商品情報の受け取り確認</h2>
<p>商品名：{$goods_name}</p>
<p>商品説明：{$description}</p>
<p>価格：{$price}円</p>
<p>商品状態：{$goods_condition}</p>
<p>カテゴリID：{$category_id}</p>
HTML;

// 入力内容のチェック

// 商品名・説明の空欄チェック
if (trim($goods_name) === '' || trim($description) === '') {
    exit('商品名と商品説明を入力してください。');
}

// 価格のチェック
if (!ctype_digit((string)$price) || (int)$price < 1) {
    exit('価格は1円以上の整数で入力してください。');
}

// 商品状態のチェック
$allowed_conditions = [
    '新品',
    '未使用',
    '目立った傷や汚れなし',
    'やや傷や汚れあり',
    '傷や汚れあり'
];

if (!in_array($goods_condition, $allowed_conditions, true)) {
    exit('正しい商品状態を選択してください。');
}

// カテゴリIDがDBに存在するか確認
$sql = "SELECT COUNT(*) FROM categories WHERE category_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$category_id]);

if ($stmt->fetchColumn() == 0) {
    exit('正しいカテゴリを選択してください。');
}

// 商品情報を登録するSQL
$sql = <<<SQL
INSERT INTO goods
(seller_id, goods_name, description, price, `condition`, category_id, listed_at)
VALUES
(:seller_id, :goods_name, :description, :price, :goods_condition, :category_id, NOW())
SQL;

// SQLを準備
$stmt = $pdo->prepare($sql);

// SQLを実行
$stmt->execute([
    ':seller_id' => $_SESSION['user_id'],
    ':goods_name' => $goods_name,
    ':description' => $description,
    ':price' => $price,
    ':goods_condition' => $goods_condition,
    ':category_id' => $category_id
]);
echo '商品情報を登録しました！';

?>