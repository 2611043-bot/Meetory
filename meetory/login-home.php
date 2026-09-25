<?php require '../meetory-header.php'; ?>

<?php
echo <<< HTML
    <p><img alt="image" src="logo.png"></p>
HTML;
?>

<form action="register.php" method="post">
    <input type="submit" value="新規登録">
</form>

<form action="login.php" method="post">
    <input type="submit" value="ログイン">
</form>
<?php require '../meetory-footer.php'; ?>