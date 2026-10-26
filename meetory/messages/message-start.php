<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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

// 共通処理
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// 商品IDを取得
$goods_id = filter_input(
    INPUT_GET,
    'goods_id',
    FILTER_VALIDATE_INT
);

// 商品IDをチェック
if (
    $goods_id === false ||
    $goods_id === null ||
    $goods_id <= 0
) {
    exit('商品IDが正しくありません。');
}

// 商品と出品者を取得
$sql = '
    SELECT
        goods_id,
        goods_name,
        seller_id,
        is_sold
    FROM goods
    WHERE goods_id = ?
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

$goods = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が存在しない場合
if (!$goods) {
    http_response_code(404);
    exit('商品が見つかりません。');
}

$seller_id = (int) $goods['seller_id'];

// 自分の商品にはDMできない
if ($seller_id === $user_id) {
    exit('自分の商品にはDMを送れません。');
}

// 出品者との既存の取引があるか確認
// 取引がある場合は、既存の取引メッセージ画面を開く
$sql = '
    SELECT DISTINCT b.transaction_id
    FROM buy AS b
    INNER JOIN buy_items AS bi
        ON b.transaction_id = bi.transaction_id
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    WHERE b.buyer_id = ?
        AND g.seller_id = ?
        AND g.goods_id = ?
    ORDER BY b.transaction_id DESC
    LIMIT 1
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $user_id,
    $seller_id,
    $goods_id
]);

$transaction_id = $stmt->fetchColumn();

if ($transaction_id !== false) {
    header(
        'Location: message-list.php?transaction_id='
        . (int) $transaction_id
        . '&partner_id='
        . $seller_id
    );
    exit;
}

// 購入前のDM画面へ移動
header(
    'Location: message-list.php?goods_id='
    . $goods_id
    . '&partner_id='
    . $seller_id
);
exit;