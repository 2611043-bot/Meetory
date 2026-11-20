<?php
session_start();
require '../../meetory-header.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードリセット | Meetory</title>

    <!-- パスワードリセット専用CSS -->
    <link rel="stylesheet" href="../assets/css/password-reset.css">
</head>
<body>

    <div class="password-reset-page">

        <div class="password-reset-card">

            <h1 class="password-reset-title">パスワードリセット</h1>

            <p class="password-reset-description">
                登録したメールアドレスを入力してください。
            </p>

            <form action="password-reset-process.php" method="post">

                <div class="form-group">
                    <label for="email">メールアドレス</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="example@example.com"
                        required
                    >
                </div>

                <button type="submit" class="reset-submit-btn">
                    リセットメールを送信
                </button>

            </form>

            <a href="login.php" class="back-login-link">
                ログイン画面に戻る
            </a>

        </div>

    </div>

</body>
</html>