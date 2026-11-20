<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../../meetory-functions.php';
$pdo = getPDO();
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) { header('Location: login.php'); exit; }
$my_id = (int)$_SESSION['user_id'];
$target_id = filter_input(INPUT_GET, 'target_id', FILTER_VALIDATE_INT);
if ($target_id === false || $target_id === null || $target_id <= 0 || $target_id === $my_id) { exit('ユーザーIDが不正です。'); }
$stmt = $pdo->prepare('SELECT user_name FROM users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $target_id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) exit('ユーザーが存在しません。');
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
require_once '../../meetory-header.php';
?>
<main class="confirm-box">
  <h1>このユーザーをブロックしますか？</h1>
  <p>ブロックすると、お互いにメッセージを送信できなくなります。過去のメッセージ履歴は削除されません。</p>
  <form method="POST" action="block-user.php" style="display:inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="target_id" value="<?= (int)$target_id ?>">
    <input type="hidden" name="redirect" value="profile.php?user_id=<?= (int)$target_id ?>">
    <button type="submit">ブロックする</button>
  </form>
  <a href="profile.php?user_id=<?= (int)$target_id ?>"><button type="button">キャンセル</button></a>
</main>
<?php require_once '../../meetory-footer.php'; ?>
