<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: ../../user/login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('この操作を行う権限がありません。');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrf_token) ||
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。');
}

$report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
if ($report_id === false || $report_id === null || $report_id <= 0) {
    exit('通報IDが正しくありません。');
}

$status = $_POST['status'] ?? '';
if (!is_string($status) || !isValidReportStatus($status)) {
    exit('対応状況が正しくありません。');
}

$pdo = getPDO();

$stmt = $pdo->prepare('SELECT 1 FROM reports WHERE report_id = ? LIMIT 1');
$stmt->execute([$report_id]);
if (!$stmt->fetchColumn()) {
    exit('通報が見つかりません。');
}

try {
    $stmt = $pdo->prepare(
        'UPDATE reports
         SET status = :status, updated_at = CURRENT_TIMESTAMP
         WHERE report_id = :report_id'
    );
    $stmt->execute([
        ':status'    => $status,
        ':report_id' => $report_id,
    ]);

    if ($stmt->rowCount() === 0) {
        exit('対応状況の更新に失敗しました。');
    }
} catch (Throwable $e) {
    error_log('report status update error: ' . $e->getMessage());
    exit('対応状況の更新に失敗しました。');
}

$_SESSION['report_status_flash'] = '対応状況を変更しました。';
header('Location: detail.php?report_id=' . $report_id);
exit;