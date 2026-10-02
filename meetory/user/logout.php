<?php

// セッションを開始
session_start();

// POST送信以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('不正なアクセスです。');
}

// CSRFトークンの確認
if (
    !isset($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('不正なリクエストです。');
}

// セッション情報をすべて削除
$_SESSION = [];

// セッションCookieの設定を取得
$params = session_get_cookie_params();

// ブラウザ側のセッションCookieを削除
if (ini_get('session.use_cookies')) {
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite']
        ]
    );
}

// セッションを破棄
session_destroy();

// ログイン画面へ移動
header('Location: ../user/login.php');
exit;

?>