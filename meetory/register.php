<?php require '../meetory-header.php'; ?>

<h1>会員登録</h1>

<form action="register-process.php" method="post">

※空白を入れないでください。

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