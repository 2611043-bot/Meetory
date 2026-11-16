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
    $redirect = isset($_POST['return_url']) && is_string($_POST['return_url'])
        ? htmlspecialchars($_POST['return_url'], ENT_QUOTES, 'UTF-8')
        : '/Meetory/meetory/user/login.php';
    header('Location: ' . $redirect);
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
    exit('不正なリクエストです。商品ページを開き直してください。');
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

// 戻り先URL（外部リダイレクト防止のため同一オリジンのみ許可）
$return_url = isset($_POST['return_url']) && is_string($_POST['return_url']) ? $_POST['return_url'] : '';
if (!preg_match('~\A/Meetory/meetory/.*\z~', $return_url)) {
    $return_url = '/Meetory/meetory/goods/goods-detail.php?goods_id=' . $goods_id;
}

try {
    // 商品の存在確認
    $stmt = $pdo->prepare(
        'SELECT goods_id
        FROM goods
        WHERE goods_id = ?'
    );
    $stmt->execute([$goods_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        exit('商品が見つかりません。');
    }

    // すでに保存済みか確認
    $stmt = $pdo->prepare(
        'SELECT favorite_id
        FROM favorites
        WHERE user_id = ?
            AND goods_id = ?'
    );
    $stmt->execute([$user_id, $goods_id]);

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        // すでに登録済みの場合は重複登録せず元のページへ戻す
        header('Location: ' . $return_url);
        exit;
    }

    // favoritesにINSERT
    $stmt = $pdo->prepare(
        'INSERT INTO favorites (user_id, goods_id)
        VALUES (?, ?)'
    );
    $stmt->execute([$user_id, $goods_id]);

    // 元の商品ページへ戻す
    header('Location: ' . $return_url);
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('ほしいものリストへの追加に失敗しました。時間をおいて再度お試しください。');
}
