<?php require '../meetory-header.php'; ?>

<?php
$pdo = getPDO();

// 新規登録画面から送られてきた情報を受け取る
$user_name = trim($_POST['user_name']);                 // ユーザー名
$email = trim($_POST['email']);                         // メールアドレス
$password = trim($_POST['password']);                   // パスワード
$password_confirm = trim($_POST['password_confirm']);   // パスワード（確認）

// ユーザー名の禁止ワード
$forbidden_words = [
    // 運営・管理者になりすます名前
    'admin',
    'administrator',
    '管理者',
    '運営',
    '運営者',
    '公式',
    'official',
    'staff',
    'support',
    'サポート',
    'system',
    'システム',
    'moderator',
    'モデレーター',
    // システム関連
    'system',
    'システム',
    'moderator',
    'モデレーター',
    // 問い合わせ・管理関係
    'customer',
    'カスタマーサポート',
    'customer_support',
    'help',
    'ヘルプ',
];

// ユーザー名に禁止ワードが含まれていないか確認
foreach ($forbidden_words as $word) {

    if (mb_stripos($user_name, $word) !== false) {
        exit('このユーザー名は使用できません。');
    }

}

// パスワードと確認用パスワードが一致しているか確認
if ($password !== $password_confirm) {
    exit('パスワードが一致しません。');
}

// ユーザー名がすでに登録されているか確認
$sql = 'SELECT user_id FROM users WHERE user_name = ?';

$stmt = $pdo->prepare($sql);

$stmt->execute([$user_name]);


// 登録済みだった場合
if ($stmt->fetch()) {
    exit('このユーザー名はすでに使用されています。');
}

// メールアドレスがすでに登録されているか確認
$sql = 'SELECT user_id FROM users WHERE email = ?';

$stmt = $pdo->prepare($sql);

$stmt->execute([$email]);


// 登録済みだった場合
if ($stmt->fetch()) {
    exit('このメールアドレスはすでに登録されています。');
}


// パスワードを暗号化
$hash = password_hash($password, PASSWORD_DEFAULT);


// ユーザー情報をデータベースに登録
$sql = 'INSERT INTO users (user_name, email, password)
        VALUES (?, ?, ?)';

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $user_name,
    $email,
    $hash
]);


// 登録完了後、ログイン画面へ移動
header('Location: login.php');
exit;
?>

<?php require '../meetory-footer.php'; ?>