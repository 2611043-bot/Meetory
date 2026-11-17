<?php

/**
 * Ajaxエンドポイント：通知設定を自動保存する。
 *
 * fetch API から POST リクエストで呼ばれる。
 * ログインユーザーの通知設定のみを更新する。
 */

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// JSONコンテンツタイプを設定
header('Content-Type: application/json; charset=utf-8');

// エラー出力を無効化（JSONレスポンスに混入させないため）
ini_set('display_errors', '0');
error_reporting(0);

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => '未ログインです',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// POSTメソッド確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => '不正なメソッドです',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 共通処理
require_once '../../meetory-functions.php';

$user_id = (int) $_SESSION['user_id'];
$pdo = getPDO();

// CSRFトークン検証
$post_csrf = $_POST['csrf_token'] ?? '';
$csrf_valid = (
    isset($_SESSION['csrf_token']) &&
    is_string($_SESSION['csrf_token']) &&
    $_SESSION['csrf_token'] !== '' &&
    is_string($post_csrf) &&
    hash_equals($_SESSION['csrf_token'], $post_csrf)
);

if (!$csrf_valid) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => '不正なリクエストです',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// オークション関連通知の単一トグルを5カラムに展開
$auctionColumns = [
    'type_auction_bid_surpassed',
    'type_auction_won',
    'type_auction_ending',
    'type_auction_bid_success',
    'type_auction_ended_seller',
];
if (isset($_POST['auction_notifications'])) {
    foreach ($auctionColumns as $col) {
        $_POST[$col] = '1';
    }
}

// 設定を保存
$success = saveNotificationSettings($pdo, $user_id, $_POST);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => '設定を保存しました',
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '保存に失敗しました。もう一度お試しください。',
    ], JSON_UNESCAPED_UNICODE);
}

exit;
