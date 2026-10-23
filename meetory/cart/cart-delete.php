<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// POST以外のアクセスを拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// データベース接続
$pdo = getPDO();

// CSRFトークン確認
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。カート画面を開き直してください。');
}

// カートIDを取得
$cart_id = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);

if (!$cart_id || $cart_id <= 0) {
    exit('カートIDが正しくありません。');
}

// ログイン中のユーザーID
$user_id = (int) $_SESSION['user_id'];

// 自分のカートに入っている商品だけ削除する
$stmt = $pdo->prepare(
    'DELETE FROM cart
    WHERE cart_id = :cart_id
        AND user_id = :user_id'
);

$stmt->execute([
    'cart_id' => $cart_id,
    'user_id' => $user_id
]);

// カート一覧に戻る
header('Location: cart-list.php');
exit;