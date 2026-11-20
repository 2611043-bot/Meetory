<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../../meetory-functions.php';
$pdo = getPDO();
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}
$user_id = (int) $_SESSION['user_id'];
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$stmt = $pdo->prepare('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC');
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
require '../../meetory-header.php';
?>
<h1>住所管理</h1>
<?php if (isset($_SESSION['flash_success'])): ?>
<p class="success-msg"><?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?></p>
<?php unset($_SESSION['flash_success']); endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
<p class="error-msg"><?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?></p>
<?php unset($_SESSION['flash_error']); endif; ?>
<p><a href="address-register.php">新しい住所を登録する</a></p>
<?php if (empty($addresses)): ?>
<p>登録された住所はありません。</p>
<?php else: ?>
<?php foreach ($addresses as $a): ?>
<div class="address-card">
    <p><?= htmlspecialchars($a['recipient_name'], ENT_QUOTES, 'UTF-8') ?>
        <?php if ($a['is_default']): ?><strong>（デフォルト）</strong><?php endif; ?></p>
    <p>〒<?= htmlspecialchars($a['postal_code'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= htmlspecialchars($a['prefecture'] . $a['city'] . $a['address_line'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= htmlspecialchars($a['building'], ENT_QUOTES, 'UTF-8') ?></p>
    <p>TEL: <?= htmlspecialchars($a['phone'], ENT_QUOTES, 'UTF-8') ?></p>
    <p>
        <a href="address-edit.php?address_id=<?= (int)$a['address_id'] ?>">編集</a>
        <a href="address-delete-confirm.php?address_id=<?= (int)$a['address_id'] ?>">削除</a>
        <?php if (!$a['is_default']): ?>
        <form method="post" action="address-default-process.php" style="display:inline">
            <input type="hidden" name="address_id" value="<?= (int)$a['address_id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">デフォルトに設定</button>
        </form>
        <?php endif; ?>
    </p>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php require '../../meetory-footer.php'; ?>
