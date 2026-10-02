<?php require '../meetory-header.php'; ?>

<?php

// セッションを使う
session_start();

// セッションの情報をすべて削除
session_destroy();

// ログイン画面へ移動
header('Location: ../user/login.php');
exit;
?>

<?php require '../meetory-footer.php'; ?>