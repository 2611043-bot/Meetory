<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('不正なアクセスです。'); }
require_once '../../meetory-functions.php';
$pdo = getPDO();
if (!isset($_SESSION['user_id']) || filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false || (int)$_SESSION['user_id'] <= 0) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$csrf = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) { exit('CSRFエラー'); }
$address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
if (!$address_id || $address_id <= 0) { exit('不正なID'); }
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT is_default FROM user_addresses WHERE address_id = ? AND user_id = ?');
    $stmt->execute([$address_id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { throw new RuntimeException('住所が見つかりません。'); }
    $wasDefault = (int)$row['is_default'] === 1;
    $pdo->prepare('DELETE FROM user_addresses WHERE address_id = ? AND user_id = ?')->execute([$address_id, $user_id]);
    if ($wasDefault) {
        $stmt = $pdo->prepare('SELECT address_id FROM user_addresses WHERE user_id = ? ORDER BY address_id ASC LIMIT 1');
        $stmt->execute([$user_id]);
        $next = $stmt->fetchColumn();
        if ($next) {
            $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE address_id = ?')->execute([$next]);
        }
    }
    $pdo->commit();
    $_SESSION['flash_success'] = '住所を削除しました。';
    unset($_SESSION['csrf_token']);
    header('Location: address-list.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    $_SESSION['flash_error'] = '削除に失敗しました。';
    header('Location: address-list.php');
    exit;
}
