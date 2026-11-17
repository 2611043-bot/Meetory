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
if ($target_id === $my_id) exit('自分自身はブロックできません。');

$stmt = $pdo->prepare('SELECT 1 FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $target_id]);
if (!$stmt->fetchColumn()) exit('ユーザーが存在しません。');

if (isUserBlocked($pdo, $my_id, $target_id)) exit('既にブロックしています。');

$stmt = $pdo->prepare('INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (:b, :d)');
$stmt->execute([':b' => $my_id, ':d' => $target_id]);

$redirect = $_POST['redirect'] ?? '../user/profile.php?user_id=' . $target_id;
header('Location: ' . $redirect);
exit;
?>