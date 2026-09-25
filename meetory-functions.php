<?php

function getPDO() {
    // データベース接続設定情報
    $host = '192.168.25.108';     // 接続先のDBサーバーのアドレス
    $dbname = 'meetory';          // 使用するDB名
    $user = 'admin';              // DBサーバーのログインID
    $password = 'meetory88125';   // DBサーバーのパスワード

    // データベース接続
    return new PDO("mysql:host={$host};dbname={$dbname};charset=utf8",
                    $user, $password);
}

function data() {
    // 新規登録画面から送られてきた情報を受け取る
    $user_name = 'user_name';                 // ユーザー名
    $email = 'email';                         // メールアドレス
    $password = 'password';                   // パスワード
    $password_confirm = 'password_confirm';   // パスワード（確認）
}

?>