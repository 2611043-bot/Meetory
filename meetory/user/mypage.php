<?php

// SESSIONを使う
session_start();


// ログインしているか確認
if (!isset($_SESSION['user_id'])) {

    // ログインしていなければログイン画面へ移動
    header('Location: login.php');
    exit;
}

?>

<?php require '../../meetory-header.php'; ?>

<h1>マイページ</h1>

<p>
    ようこそ、<?php echo $_SESSION['user_name']; ?>さん
</p>

<a href="logout.php">ログアウト</a>

<?php require '../../meetory-footer.php'; ?>