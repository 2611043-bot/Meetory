
<?php
// セッションを先に開始する
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 一時的な確認用コード
echo '登録処理：トークンあり=' .
    (isset($_SESSION['csrf_token']) ? 'YES' : 'NO');

require '../../meetory-header.php';

$pdo = getPDO();

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['register_errors'] = ['common' => 'セッションにCSRFトークンがありません。'];
    $_SESSION['register_old'] = $_POST;
    header('Location: register.php');
    exit;
}

if ($csrf_token === '') {
    $_SESSION['register_errors'] = ['common' => 'フォームからCSRFトークンが送信されていません。'];
    $_SESSION['register_old'] = $_POST;
    header('Location: register.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    $_SESSION['register_errors'] = ['common' => 'CSRFトークンが一致しません。'];
    $_SESSION['register_old'] = $_POST;
    header('Location: register.php');
    exit;
}

// 新規登録画面から送られてきた情報を受け取る
$user_name = trim($_POST['user_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// 入力値を保持するための配列
$old_data = [
    'user_name' => $user_name,
    'email' => $email,
];

// エラーを保存してリダイレクトするヘルパー
function setRegisterErrorAndRedirect(string $field, string $message, array $old_data): void
{
    $_SESSION['register_errors'] = [$field => $message];
    $_SESSION['register_old'] = $old_data;
    header('Location: register.php');
    exit;
}

// 未入力チェック
if ($user_name === '') {
    setRegisterErrorAndRedirect('user_name', 'ユーザー名を入力してください。', $old_data);
}

if ($email === '') {
    setRegisterErrorAndRedirect('email', 'メールアドレスを入力してください。', $old_data);
}

if ($password === '') {
    setRegisterErrorAndRedirect('password', 'パスワードを入力してください。', $old_data);
}

if ($password_confirm === '') {
    setRegisterErrorAndRedirect('password_confirm', 'パスワード（確認）を入力してください。', $old_data);
}

// 文字数チェック
if (mb_strlen($user_name) < 2 || mb_strlen($user_name) > 10) {
    setRegisterErrorAndRedirect('user_name', 'ユーザー名は2～10文字で入力してください。', $old_data);
}

// ユーザー名に空白が含まれていないか確認
if (preg_match('/[\s　]/u', $user_name)) {
    setRegisterErrorAndRedirect('user_name', 'ユーザー名に空白を入れないでください。', $old_data);
}

if (mb_strlen($password) < 8 || mb_strlen($password) > 16) {
    setRegisterErrorAndRedirect('password', 'パスワードは8～16文字で入力してください。', $old_data);
}
// パスワードの強度チェック
if (!preg_match('/[A-Za-z]/', $password)) {
    setRegisterErrorAndRedirect('password', 'パスワードには英字を1文字以上含めてください。', $old_data);
}

if (!preg_match('/[0-9]/', $password)) {
    setRegisterErrorAndRedirect('password', 'パスワードには数字を1文字以上含めてください。', $old_data);
}

// メールアドレスの形式チェック
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setRegisterErrorAndRedirect('email', '正しいメールアドレスを入力してください。', $old_data);
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
    'meetory',
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
        setRegisterErrorAndRedirect('user_name', 'このユーザー名は使用できません。', $old_data);
    }

}

// パスワードと確認用パスワードが一致しているか確認
if ($password !== $password_confirm) {
    setRegisterErrorAndRedirect('password_confirm', 'パスワードが一致しません。', $old_data);
}

// ユーザー名がすでに登録されているか確認
$sql = 'SELECT user_id FROM users WHERE user_name = ?';

$stmt = $pdo->prepare($sql);

$stmt->execute([$user_name]);


// 登録済みだった場合
if ($stmt->fetch()) {
    setRegisterErrorAndRedirect('user_name', 'このユーザー名は既に使用されています。', $old_data);
}

// メールアドレスがすでに登録されているか確認
$sql = 'SELECT user_id FROM users WHERE email = ?';

$stmt = $pdo->prepare($sql);

$stmt->execute([$email]);


// 登録済みだった場合
if ($stmt->fetch()) {
    setRegisterErrorAndRedirect('email', 'このメールアドレスは既に使用されています。', $old_data);
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
    $_SESSION['register_errors'] = ['common' => '登録処理中にエラーが発生しました。'];
    $_SESSION['register_old'] = $old_data;
    header('Location: register.php');
    exit;
}

// CSRFトークンを破棄
unset($_SESSION['csrf_token']);

// 登録完了後、ログイン画面へ移動
header('Location: ../user/login.php');
exit;
?>

<?php require '../../meetory-footer.php'; ?>
