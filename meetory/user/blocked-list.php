<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../../meetory-functions.php';
$pdo = getPDO();
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) { header('Location: login.php'); exit; }
$my_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$stmt = $pdo->prepare('SELECT ub.blocked_id, ub.created_at, u.user_name, u.profile_image_type, u.profile_image FROM user_blocks ub JOIN users u ON u.user_id = ub.blocked_id WHERE ub.blocker_id = :id ORDER BY ub.created_at DESC');
$stmt->execute([':id' => $my_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once '../../meetory-header.php';
?>
<style>
.blocked-item { display: flex; align-items: center; gap: 12px; }
.blocked-user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
</style>
<main class="blocked-list">
<h1>ブロック一覧</h1>
<?php if (empty($rows)): ?>
<p>ブロックしているユーザーはいません</p>
<?php else: ?>
<ul>
<?php foreach ($rows as $r): ?>
<li class="blocked-item">
<?= renderProfileAvatar($r, 'blocked-user-avatar', '') ?>
<span class="blocked-name"><?= htmlspecialchars($r['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
<span class="blocked-date"><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
<form method="POST" action="unblock-user.php" style="display:inline">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="target_id" value="<?= (int)$r['blocked_id'] ?>">
<button type="submit">ブロック解除</button>
</form>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</main>
<?php require_once '../../meetory-footer.php'; ?>
