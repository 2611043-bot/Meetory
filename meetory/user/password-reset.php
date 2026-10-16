<?php
session_start();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードリセット</title>
</head>
<body>
    <h1>パスワードリセット</h1>
    <p>
        登録したメールアドレスを入力してください。
    </p>

    <form action="password-reset-process.php" method="post">
        <p>
            メールアドレス
            <input type="email" name="email" required>
        </p>
        <input type="submit" value="リセットURLを送信">

        <p>
            <a href="login.php">ログイン画面に戻る</a>
        </p>
        </body>
</html>
