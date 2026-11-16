
<?php

require_once '../../meetory-functions.php';

session_set_cookie_params([
    'httponly' => true,
    //'secure' => true,
    'samesite' => 'Lax'
]);

session_start();

$pdo = getPDO();

// ログイン画面から送られた情報を受け取る
$user_name = trim($_POST['user_name'] ?? '');
$password = $_POST['password'] ?? '';

// 入力チェック
if ($user_name === '') {
    exit('ユーザー名を入力してください。');
}

if ($password === '') {
    exit('パスワードを入力してください。');
}

// ユーザー名からユーザー情報を取得
$sql = 'SELECT * FROM users WHERE user_name = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_name]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ユーザーが存在し、パスワードが正しいか確認
if ($user && password_verify($password, $user['password'])) {

    // セッションIDを新しくする
    session_regenerate_id(true);

    // ログイン状態を保存
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['user_name'];
    $_SESSION['role'] = $user['role'] ?? 'user';

    // 権限を保存（一般ユーザーはuser、管理者はadmin）
    $_SESSION['role'] = $user['role'] ?? 'user';

    // ログイン後のページへ移動
    $return_url = $_POST['return_url'] ?? '';
    if ($return_url !== '' && is_string($return_url) && preg_match('~\A/Meetory/meetory/.*\z~', $return_url)) {
        header('Location: ' . $return_url);
    } else {
        header('Location: ../index.php');
    }
    exit;

} else {

    // ログイン失敗：ログイン画面へ戻る（エラーフラグとユーザー名を保持）
    $redirect_url = 'login.php?error=1&user_name=' . urlencode($user_name);
    header('Location: ' . $redirect_url);
    exit;

}
?>