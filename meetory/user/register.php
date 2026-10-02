<?php require '../meetory-header.php'; ?>

<h1>会員登録</h1>

<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<form action="register-process.php" method="post">


<input type="hidden" name="csrf_token" 
value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

※空白を入れないでください。パスワードには英字、数字を１文字以上含めてください。

ユーザー名
<input type="text" name="user_name" required>
メールアドレス
<input type="email" name="email" placeholder="例) meetory@example.com" required>
パスワード
<input type="password" name="password" required>
パスワード(確認)
<input type="password" name="password_confirm" required>
<input type="submit" value="登録">

</form>

<a href="login.php">ログインはこちら</a>

<?php require '../meetory-footer.php'; ?>