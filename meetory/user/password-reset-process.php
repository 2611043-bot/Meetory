
<?php
session_start();

require_once '../../meetory-functions.php';

$pdo = getPDO();

// POST以外のアクセスを拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: password-reset.php');
    exit;
}

// メールアドレスを取得
$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('メールアドレスの形式が正しくありません。');
}

// ユーザーを検索
$stmt = $pdo->prepare(
    'SELECT user_id FROM users WHERE email = :email'
);
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 登録されていないメールアドレスの場合
if (!$user) {
    exit('リセット申請を受け付けました。登録済みの場合は処理が行われます。');
}

// ランダムなトークンを生成
$token = bin2hex(random_bytes(32));

// DBにはトークンのハッシュ値を保存
$tokenHash = hash('sha256', $token);

// 有効期限を30分後に設定
$expiresAt = date('Y-m-d H:i:s', time() + 1800);

// 以前のリセット情報を削除
$stmt = $pdo->prepare(
    'DELETE FROM password_resets WHERE user_id = :user_id'
);
$stmt->execute(['user_id' => $user['user_id']]);

// 新しいリセット情報を保存
$stmt = $pdo->prepare(
    'INSERT INTO password_resets
        (user_id, token, expires_at)
    VALUES
        (:user_id, :token, :expires_at)'
);

$stmt->execute([
    'user_id' => $user['user_id'],
    'token' => $tokenHash,
    'expires_at' => $expiresAt
]);

// テスト用リセットURLを生成
$resetUrl = 'http://' . $_SERVER['HTTP_HOST']
    . '/Meetory/meetory/user/password-new.php?token='
    . urlencode($token);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リセットURL発行 | Meetory</title>
</head>
<body>

    <h1>パスワードリセット申請</h1>

    <p>リセット申請を受け付けました。</p>

    <h2>テスト用リセットURL</h2>

    <p>
        <a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>">
            リセット画面を開く
        </a>
    </p>

    <p>URLの有効期限は30分です。</p>

    <p>
        <a href="login.php">ログイン画面に戻る</a>
    </p>

</body>
</html>
