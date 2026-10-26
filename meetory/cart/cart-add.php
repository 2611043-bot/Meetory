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
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
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
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === '' ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。商品詳細画面を開き直してください。');
}

// 商品IDを取得
$goods_id = filter_input(INPUT_POST, 'goods_id', FILTER_VALIDATE_INT);

if (
    $goods_id === false ||
    $goods_id === null ||
    $goods_id <= 0
) {
    exit('商品IDが正しくありません。');
}
// ユーザーID
$user_id = (int) $_SESSION['user_id'];

try {

    // 商品情報を取得
    $stmt = $pdo->prepare(
        'SELECT goods_id, seller_id, is_sold
        FROM goods
        WHERE goods_id = ?'
    );

    $stmt->execute([$goods_id]);

    $goods = $stmt->fetch(PDO::FETCH_ASSOC);

    // 商品の存在確認
    if (!$goods) {
        exit('商品が見つかりません。');
    }

    // 自分の商品は追加できない
    if ((int) $goods['seller_id'] === $user_id) {
        exit('自分が出品した商品はカートに追加できません。');
    }

    // 売却済み商品は追加できない
    if ((int) $goods['is_sold'] !== 0) {
        exit('売却済みの商品はカートに追加できません。');
    }

    // すでにカートに入っているか確認
    $stmt = $pdo->prepare(
        'SELECT cart_id
        FROM cart
        WHERE user_id = ?
            AND goods_id = ?'
    );

    $stmt->execute([$user_id, $goods_id]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        exit('この商品はすでにカートに入っています。');
    }

    // カートに商品を追加
    $stmt = $pdo->prepare(
        'INSERT INTO cart (user_id, goods_id)
        VALUES (?, ?)'
    );

    $stmt->execute([$user_id, $goods_id]);

    // カート一覧へ移動
    header('Location: cart-list.php');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    exit('カートへの追加に失敗しました。時間をおいて再度お試しください。');
}