
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // GmailのSMTP設定
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'meetory6@gmail.com';
    $mail->Password = 'npmi oxuw euwc lynw';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    // 文字コード設定
    $mail->CharSet = 'UTF-8';

    // 送信元
    $mail->setFrom(
        'meetory6@gmail.com',
        'Meetory'
    );

    // 送信先（テストでは自分のメールアドレス）
    $mail->addAddress('2610029@i-seifu.jp');

    // メールの内容
    $mail->isHTML(false);
    $mail->Subject = 'Meetory メール送信テスト';
    $mail->Body = "これはMeetoryからのテストメールです。\nメール送信に成功しました。";

    // メール送信
    $mail->send();

    echo 'メール送信に成功しました！';

} catch (Exception $e) {
    echo 'メール送信に失敗しました。';
    error_log($mail->ErrorInfo);
}
