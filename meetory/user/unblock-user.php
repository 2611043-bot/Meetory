<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../../meetory-functions.php';
$pdo = getPDO();

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header('Location: login.php'); exit;
}
$my_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) { exit('不正なリクエストです。'); }

$target_id = filter_input(INPUT_POST, 'target_id', FILTER_VALIDATE_INT);
if ($target_id === false || $target_id === null || $target_id <= 0) exit('ユーザーIDが不正です。');

$stmt = $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = :b AND blocked_id = :d');
$stmt->execute([':b' => $my_id, ':d' => $target_id]);

$redirect = $_POST['redirect'] ?? '../user/blocked-list.php';
header('Location: ' . $redirect);
exit;
?>