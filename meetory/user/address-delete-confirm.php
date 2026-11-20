<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once '../../meetory-functions.php';
$pdo = getPDO();
if (!isset($_SESSION['user_id']) || filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false || (int)$_SESSION['user_id'] <= 0) { header('Location: login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$address_id = filter_input(INPUT_GET, 'address_id', FILTER_VALIDATE_INT);
if (!$address_id || $address_id <= 0) { exit('不正なID'); }
$stmt = $pdo->prepare('SELECT * FROM user_addresses WHERE address_id = ? AND user_id = ?');
$stmt->execute([$address_id, $user_id]);
$addr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$addr) { exit('住所が見つかりません。'); }
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
require '../../meetory-header.php';
?>
<h1>住所削除確認</h1>
<p>以下の住所を削除しますか？</p>
<p><?= htmlspecialchars($addr['recipient_name'], ENT_QUOTES, 'UTF-8') ?></p>
<p>〒<?= htmlspecialchars($addr['postal_code'], ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($addr['prefecture'].$addr['city'].$addr['address_line'], ENT_QUOTES, 'UTF-8') ?></p>
<form method="post" action="address-delete-process.php">
<input type="hidden" name="address_id" value="<?= (int)$addr['address_id'] ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
<button type="submit">削除する</button>
<a href="address-list.php">キャンセル</a>
</form>
<?php require '../../meetory-footer.php'; ?>
