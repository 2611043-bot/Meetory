<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('不正なアクセスです。');
}

require_once '../../meetory-functions.php';

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrf_token) ||
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    http_response_code(400);
    exit('不正なリクエストです。ページを開き直してください。');
}

$submission_token = $_POST['submission_token'] ?? '';
if (
    !is_string($submission_token) ||
    !isset($_SESSION['contact_submission_tokens']) ||
    !is_array($_SESSION['contact_submission_tokens']) ||
    !isset($_SESSION['contact_submission_tokens'][$submission_token])
) {
    http_response_code(409);
    exit('このお問い合わせは送信済みか、フォームの有効期限が切れています。');
}

$category = $_POST['category'] ?? '';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
$category = is_string($category) ? trim($category) : '';
$subject = is_string($subject) ? trim($subject) : '';
$message = is_string($message) ? trim($message) : '';

$errors = [];
if (!in_array($category, getInquiryCategories(), true)) {
    $errors[] = 'お問い合わせカテゴリを選択してください。';
}
if (
    $subject === '' ||
    !mb_check_encoding($subject, 'UTF-8') ||
    mb_strlen($subject, 'UTF-8') > 200 ||
    preg_match('/[\r\n\x00]/', $subject)
) {
    $errors[] = '件名は1〜200文字で入力してください。';
}
if (
    $message === '' ||
    !mb_check_encoding($message, 'UTF-8') ||
    mb_strlen($message, 'UTF-8') > 10000 ||
    strpos($message, "\0") !== false
) {
    $errors[] = 'お問い合わせ内容は1〜10,000文字で入力してください。';
}

if (!empty($errors)) {
    $_SESSION['contact_form_data'] = [
        'category' => $category,
        'subject' => $subject,
        'message' => $message
    ];
    $_SESSION['contact_form_errors'] = $errors;
    header('Location: contact.php');
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'INSERT INTO inquiries (user_id, category, subject, message, status)
         VALUES (:user_id, :category, :subject, :message, :status)'
    );
    $stmt->execute([
        'user_id' => (int) $_SESSION['user_id'],
        'category' => $category,
        'subject' => $subject,
        'message' => $message,
        'status' => '未対応'
    ]);

    $inquiry_id = (int) $pdo->lastInsertId();
    unset($_SESSION['contact_submission_tokens'][$submission_token]);
    $_SESSION['created_inquiry_id'] = $inquiry_id;
    header('Location: inquiry-detail.php?inquiry_id=' . $inquiry_id);
    exit;
} catch (Throwable $e) {
    error_log('inquiry creation failed: ' . $e->getMessage());
    $_SESSION['contact_form_data'] = [
        'category' => $category,
        'subject' => $subject,
        'message' => $message
    ];
    $_SESSION['contact_form_errors'] = [
        'お問い合わせを保存できませんでした。時間をおいて再度お試しください。'
    ];
    header('Location: contact.php');
    exit;
}
