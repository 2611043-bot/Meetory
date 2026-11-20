<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../../user/login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('この操作を行う権限がありません。');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('不正なアクセスです。');
}

require_once '../../../meetory-functions.php';

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

$raw_inquiry_id = $_POST['inquiry_id'] ?? null;
$inquiry_id = is_string($raw_inquiry_id)
    ? filter_var($raw_inquiry_id, FILTER_VALIDATE_INT)
    : false;
$action = $_POST['action'] ?? '';
if (
    $inquiry_id === false ||
    $inquiry_id === null ||
    $inquiry_id <= 0 ||
    !is_string($action) ||
    !in_array($action, ['reply', 'change_status'], true)
) {
    http_response_code(400);
    exit('入力内容が正しくありません。');
}

$redirect_url = 'inquiry-detail.php?inquiry_id=' . $inquiry_id;
$failure_message = '';
$reply = '';
$status = '';
$mark_answered = false;

if ($action === 'reply') {
    $reply_input = $_POST['message'] ?? '';
    $reply = is_string($reply_input) ? trim($reply_input) : '';
    $mark_value = $_POST['mark_answered'] ?? '';
    if ($mark_value !== '' && $mark_value !== '1') {
        $failure_message = '返信内容が正しくありません。';
    } elseif (
        $reply === '' ||
        !mb_check_encoding($reply, 'UTF-8') ||
        mb_strlen($reply, 'UTF-8') > 10000 ||
        strpos($reply, "\0") !== false
    ) {
        $failure_message = '返信内容は1〜10,000文字で入力してください。';
    } else {
        $mark_answered = $mark_value === '1';
    }
} else {
    $status_input = $_POST['status'] ?? '';
    $status = is_string($status_input) ? $status_input : '';
    if (!isValidInquiryStatus($status)) {
        $failure_message = '対応状況を選択してください。';
    }
}

if ($failure_message !== '') {
    $_SESSION['inquiry_admin_flash'] = $failure_message;
    header('Location: ' . $redirect_url);
    exit;
}

$pdo = null;
try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $lock_stmt = $pdo->prepare(
        'SELECT inquiry_id FROM inquiries WHERE inquiry_id = :inquiry_id FOR UPDATE'
    );
    $lock_stmt->execute(['inquiry_id' => $inquiry_id]);
    if ($lock_stmt->fetchColumn() === false) {
        $pdo->rollBack();
        $_SESSION['inquiry_admin_flash'] = 'お問い合わせが見つかりません。';
        header('Location: inquiries.php');
        exit;
    }

    if ($action === 'reply') {
        $message_stmt = $pdo->prepare(
            'INSERT INTO inquiry_messages
                (inquiry_id, sender_id, sender_type, message)
             VALUES
                (:inquiry_id, :sender_id, :sender_type, :message)'
        );
        $message_stmt->execute([
            'inquiry_id' => $inquiry_id,
            'sender_id' => (int) $_SESSION['user_id'],
            'sender_type' => 'admin',
            'message' => $reply
        ]);

        $update_sql = $mark_answered
            ? 'UPDATE inquiries SET status = :status, updated_at = CURRENT_TIMESTAMP
               WHERE inquiry_id = :inquiry_id'
            : 'UPDATE inquiries SET updated_at = CURRENT_TIMESTAMP
               WHERE inquiry_id = :inquiry_id';
        $update_stmt = $pdo->prepare($update_sql);
        $update_params = ['inquiry_id' => $inquiry_id];
        if ($mark_answered) {
            $update_params['status'] = '回答済み';
        }
        $update_stmt->execute($update_params);
        $_SESSION['inquiry_admin_flash'] = '返信を保存しました。';
    } else {
        $update_stmt = $pdo->prepare(
            'UPDATE inquiries
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE inquiry_id = :inquiry_id'
        );
        $update_stmt->execute([
            'status' => $status,
            'inquiry_id' => $inquiry_id
        ]);
        $_SESSION['inquiry_admin_flash'] = '対応状況を更新しました。';
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('admin inquiry update failed: ' . $e->getMessage());
    $_SESSION['inquiry_admin_flash'] = '処理に失敗しました。時間をおいて再度お試しください。';
}

header('Location: ' . $redirect_url);
exit;
