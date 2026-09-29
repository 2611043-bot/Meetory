<?php

// セッション開始
session_start();

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require '../meetory-functions.php';

// DB接続
$pdo = getPDO();

?>