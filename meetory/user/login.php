<?php require '../../meetory-header.php'; ?>

<h1>ログイン</h1>

<form action="login-process.php" method="post">
ユーザー名
<input type="text" name="user_name" required>
パスワード
<input type="password" name="password" required>
<input type="submit" value="ログイン">
<p>
<a href="password-reset.php">パスワードを忘れた場合</a>
</p>
<p>
<a href="register.php">新規登録はこちら</a>
</p>
</form>
<?php require '../../meetory-footer.php'; ?>