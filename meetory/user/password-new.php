
<?php
require_once '../../meetory-functions.php';

$pdo = getPDO();

// URLからトークンを取得
$token = $_GET['token'] ?? '';

// トークンの形式を確認
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    exit('リセットURLが正しくありません。');
}

// DBに保存したハッシュ値と照合
$tokenHash = hash('sha256', $token);


$stmt = $pdo->prepare(
    'SELECT reset_id
    FROM password_resets
    WHERE token = :token
    AND expires_at > DATE_ADD(UTC_TIMESTAMP(), INTERVAL 9 HOUR)'
);


$stmt->execute(['token' => $tokenHash]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset) {
    $stmt = $pdo->prepare(
    'SELECT reset_id, expires_at
    FROM password_resets
    WHERE token = :token'
);
}
$stmt->execute(['token' => $tokenHash]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

// トークンが存在しない場合
if (!$reset) {
    exit('リセットURLが無効です。');
}

// PHP側で有効期限を確認
if (strtotime($reset['expires_at']) <= time()) {
    exit('リセットURLの有効期限が切れています。');
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新しいパスワードの設定 | Meetory</title>
</head>
<body>

    <h1>新しいパスワードの設定</h1>

    <p>新しいパスワードを入力してください。</p>

    <form action="password-update.php" method="post">
        <input
            type="hidden"
            name="token"
            value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="password">新しいパスワード</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            minlength="8"
            autocomplete="new-password"
        >

        <br>

        <label for="password_confirm">新しいパスワード（確認）</label>
        <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            required
            minlength="8"
            autocomplete="new-password"
        >

        <br>

        <button type="submit">パスワードを変更する</button>
    </form>

</body>
</html>
