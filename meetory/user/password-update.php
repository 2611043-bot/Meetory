
<?php
require_once '../../meetory-functions.php';

$pdo = getPDO();

// POST以外のアクセスを拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: password-reset.php');
    exit;
}

// 入力値を取得
$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

// トークンの形式を確認
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    exit('リセットURLが正しくありません。');
}

// パスワードの入力確認
if ($password === '' || $passwordConfirm === '') {
    exit('パスワードを入力してください。');
}

// パスワードの一致確認
if ($password !== $passwordConfirm) {
    exit('2つのパスワードが一致しません。');
}

// パスワードの長さを確認（8文字以上）
if (strlen($password) < 8) {
    exit('パスワードは8文字以上で入力してください。');
}

// トークンをハッシュ化
$tokenHash = hash('sha256', $token);

// トークンを検索
$stmt = $pdo->prepare(
    'SELECT reset_id, user_id, expires_at
    FROM password_resets
    WHERE token = :token'
);

$stmt->execute(['token' => $tokenHash]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

// トークンの存在確認
if (!$reset) {
    exit('リセットURLが無効です。');
}

// 有効期限を確認
if (strtotime($reset['expires_at']) <= time()) {
    exit('リセットURLの有効期限が切れています。');
}

// パスワードをハッシュ化
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // パスワードを更新
    $stmt = $pdo->prepare(
        'UPDATE users
        SET password = :password
        WHERE user_id = :user_id'
    );

    $stmt->execute([
        'password' => $passwordHash,
        'user_id' => $reset['user_id']
    ]);

    // 使用済みのリセット情報を削除
    $stmt = $pdo->prepare(
        'DELETE FROM password_resets
        WHERE reset_id = :reset_id'
    );

    $stmt->execute([
        'reset_id' => $reset['reset_id']
    ]);

    $pdo->commit();

    echo 'パスワードを変更しました。';
    echo '<p><a href="login.php">ログイン画面へ戻る</a></p>';

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    exit('パスワードの変更に失敗しました。');
}