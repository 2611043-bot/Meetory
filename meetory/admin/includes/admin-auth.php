<?php

// 管理者専用共通認証ファイル

// セッションが開始されていなければ開始する
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 共通関数を読み込む
require_once __DIR__ . '/../../../meetory-functions.php';

// ログインチェック
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/admin/index.php');
    header('Location: ../../user/login.php?return_url=' . urlencode($return));
    exit;
}

// 管理者権限チェック
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('このページを閲覧する権限がありません。');
}

// CSRFトークン（既存を上書きしない）
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
