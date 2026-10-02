<?php

function getPDO()
{
    $config = require __DIR__ . '/db-config.php';

    return new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['password']
    );
}

function data() {
    // 新規登録画面から送られてきた情報を受け取る
    $user_name = 'user_name';                 // ユーザー名
    $email = 'email';                         // メールアドレス
    $password = 'password';                   // パスワード
    $password_confirm = 'password_confirm';   // パスワード（確認）
}

?>