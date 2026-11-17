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
$stmt = $pdo->prepare('SELECT address_id FROM user_addresses WHERE address_id = ? AND user_id = ?');
$stmt->execute([$address_id, $user_id]);
if (!$stmt->fetch()) { exit('住所が見つかりません。'); }
$recipient_name = trim((string)($_POST['recipient_name'] ?? ''));
$postal_code = preg_replace('/[^0-9]/', '', (string)($_POST['postal_code'] ?? ''));
$prefecture = trim((string)($_POST['prefecture'] ?? ''));
$city = trim((string)($_POST['city'] ?? ''));
$address_line = trim((string)($_POST['address_line'] ?? ''));
$building = trim((string)($_POST['building'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$is_default = isset($_POST['is_default']) ? 1 : 0;
if ($recipient_name === '' || mb_strlen($recipient_name) > 100) { $_SESSION['flash_error']='氏名を確認してください'; header('Location: address-edit.php?address_id='.$address_id); exit; }
if (strlen($postal_code) !== 7) { $_SESSION['flash_error']='郵便番号は7桁で入力してください'; header('Location: address-edit.php?address_id='.$address_id); exit; }
if ($prefecture === '') { $_SESSION['flash_error']='都道府県を選択してください'; header('Location: address-edit.php?address_id='.$address_id); exit; }
if ($city === '' || mb_strlen($city) > 100) { $_SESSION['flash_error']='市区町村を確認してください'; header('Location: address-edit.php?address_id='.$address_id); exit; }
if ($address_line === '' || mb_strlen($address_line) > 255) { $_SESSION['flash_error']='番地を確認してください'; header('Location: address-edit.php?address_id='.$address_id); exit; }
if ($phone === '' || !preg_match('/^[0-9\-]{10,20}$/', $phone)) { $_SESSION['flash_error']='電話番号を確認してください'; header('Location: address-edit.php?address_id='.$address_id); exit; }
try {
    $pdo->beginTransaction();
    if ($is_default) {
        $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?')->execute([$user_id]);
    }
    $stmt = $pdo->prepare('UPDATE user_addresses SET recipient_name=?, postal_code=?, prefecture=?, city=?, address_line=?, building=?, phone=?, is_default=? WHERE address_id=? AND user_id=?');
    $stmt->execute([$recipient_name, $postal_code, $prefecture, $city, $address_line, $building, $phone, $is_default, $address_id, $user_id]);
    $pdo->commit();
    $_SESSION['flash_success'] = '住所を更新しました。';
    unset($_SESSION['csrf_token']);
    header('Location: address-list.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    $_SESSION['flash_error'] = '更新に失敗しました。';
    header('Location: address-edit.php?address_id='.$address_id);
    exit;
}
