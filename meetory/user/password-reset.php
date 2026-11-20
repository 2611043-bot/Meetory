<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/login.css">
<link rel="stylesheet" href="../assets/css/password-reset.css">

<div class="login-page">

    <div class="login-card">

        <h1 class="login-title">パスワードリセット</h1>

        <p class="login-description">
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

            <button type="submit" class="login-button">
                リセットメールを送信
            </button>

        </form>

        <p class="register-link">
            <a href="login.php">ログイン画面に戻る</a>
        </p>

    </div>

</div>