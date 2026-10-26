
<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;

require_once '../../meetory-functions.php';
require_once '../vendor/autoload.php';

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


// ここから下はChatGPTとにらめっこ

// 登録されていないメールアドレスの場合
if (!$user) {
    exit('リセット申請を受け付けました。登録済みの場合は処理が行われます。');
}

// 登録済みのユーザーの場合のみリセット処理を行う
if ($user) {
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

    // リセットURLを生成
    $isHttps = (
        isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off'
    );

    $scheme = $isHttps ? 'https' : 'http';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    $resetUrl = $scheme . '://' . $_SERVER['HTTP_HOST']
        . $basePath . '/password-new.php?token='
        . urlencode($token);

    try {
        // Gmailの設定を読み込む
        $mailConfig = require '../../mail-config.php';

        $mail = new PHPMailer(true);

        // GmailのSMTP設定
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // 文字コード設定
        $mail->CharSet = 'UTF-8';

        // 送信元と送信先
        $mail->setFrom(
            $mailConfig['username'],
            'Meetory'
        );
        $mail->addAddress($email);

        // メール本文
        $mail->isHTML(false);
        $mail->Subject = 'パスワード再設定のご案内';
        $mail->Body =
            "パスワードの再設定が申請されました。\n\n"
            . "以下のURLから新しいパスワードを設定してください。\n\n"
            . $resetUrl . "\n\n"
            . "このURLの有効期限は30分です。\n"
            . "心当たりがない場合は、このメールを無視してください。";

        // メール送信
        $mail->send();

    } catch (\Throwable $e) {
        // 詳細はサーバーのエラーログに記録する
        error_log('Meetory password reset mail error: '
            . $e->getMessage());

        // 送信に失敗したリセット情報を削除
        $stmt = $pdo->prepare(
            'DELETE FROM password_resets WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $user['user_id']]);
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リセットメールを送信 | Meetory</title>
</head>
<body>

    <h1>パスワードリセット申請</h1>

    <p>リセット申請を受け付けました。</p>

    <p>URLの有効期限は30分です。</p>

    <p>
        <a href="login.php">ログイン画面に戻る</a>
    </p>

</body>
</html>
