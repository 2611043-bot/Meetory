<?php require '../meetory-header.php'; ?>

<?php

session_start();

$pdo = getPDO();

// ログイン画面から送られてきた情報を受け取る
$user_name = trim($_POST['user_name']);
$password = trim($_POST['password']);


// ユーザー名からユーザー情報を取得
$sql = 'SELECT * FROM users WHERE user_name = ?';

$stmt = $pdo->prepare($sql);

$stmt->execute([$user_name]);


// ユーザー情報を取得
$user = $stmt->fetch(PDO::FETCH_ASSOC);


// ユーザーが存在し、パスワードが正しいか確認
if ($user && password_verify($password, $user['password'])) {

    // ログイン状態を保存
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['user_name'];

    // ログイン後のページへ移動
    header('Location: goods-register.php');
    exit;

} else {

    // ログイン失敗
    exit('ユーザー名またはパスワードが間違っています。');

}
?>

<?php require '../meetory-footer.php'; ?>