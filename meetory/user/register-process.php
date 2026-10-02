<?php require '../meetory-header.php'; ?>

<?php
$pdo = getPDO();

// CSRF対策
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。');
}

// 新規登録画面から送られてきた情報を受け取る
$user_name = trim($_POST['user_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// 未入力チェック
if ($user_name === '') {
    exit('ユーザー名を入力してください。');
}

if ($email === '') {
    exit('メールアドレスを入力してください。');
}

if ($password === '') {
    exit('パスワードを入力してください。');
}

if ($password_confirm === '') {
    exit('パスワード（確認）を入力してください。');
}

// 文字数チェック
if (mb_strlen($user_name) < 2 || mb_strlen($user_name) > 10) {
    exit('ユーザー名は2～10文字で入力してください。');
}

// ユーザー名に空白が含まれていないか確認
if (preg_match('/[\s　]/u', $user_name)) {
    exit('ユーザー名に空白を入れないでください。');
}

if (mb_strlen($password) < 8 || mb_strlen($password) > 16) {
    exit('パスワードは8～16文字で入力してください。');
}
// パスワードの強度チェック
if (!preg_match('/[A-Za-z]/', $password)) {
    exit('パスワードには英字を1文字以上含めてください。');
}

if (!preg_match('/[0-9]/', $password)) {
    exit('パスワードには数字を1文字以上含めてください。');
}

// メールアドレスの形式チェック
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('正しいメールアドレスを入力してください。');
}

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

try {
    $stmt->execute([
        $user_name,
        $email,
        $hash
    ]);
} catch (PDOException $e) {
    exit('登録処理中にエラーが発生しました。');
}

// CSRFトークンを破棄
unset($_SESSION['csrf_token']);

// 登録完了後、ログイン画面へ移動
header('Location: ../user/login.php');
exit;
?>

<?php require '../meetory-footer.php'; ?>