<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/reports/admin/restriction-history.php');
    header('Location: ../../user/login.php?return_url=' . urlencode($return));
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('このページを閲覧する権限がありません。');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = getPDO();

$target_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if ($target_user_id === false || $target_user_id === null || $target_user_id <= 0) {
    exit('ユーザーIDが正しくありません。');
}

$stmt = $pdo->prepare('SELECT user_id, user_name FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target_user) {
    exit('ユーザーが見つかりません。');
}

$history = getRestrictionHistory($pdo, $target_user_id);

require '../../../meetory-header.php';
?>

<link rel="stylesheet" href="../../assets/css/reports.css">

<main class="report-page report-admin">

    <h1>制限履歴：<?= htmlspecialchars($target_user['user_name'], ENT_QUOTES, 'UTF-8') ?></h1>

    <p>
        <a href="restriction-form.php?user_id=<?= (int) $target_user_id ?>">制限管理に戻る</a>
        |
        <a href="users.php">ユーザー一覧に戻る</a>
    </p>

    <?php if (empty($history)): ?>
        <p>履歴はありません。</p>
    <?php else: ?>
        <table class="report-list">
            <thead>
                <tr>
                    <th>日時</th>
                    <th>操作</th>
                    <th>種類</th>
                    <th>理由</th>
                    <th>管理者</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                    <?php
                    $adminName = '(不明)';
                    if (!empty($h['admin_id'])) {
                        $aStmt = $pdo->prepare('SELECT user_name FROM users WHERE user_id = ? LIMIT 1');
                        $aStmt->execute([(int) $h['admin_id']]);
                        $aRow = $aStmt->fetch(PDO::FETCH_ASSOC);
                        if ($aRow) {
                            $adminName = htmlspecialchars($aRow['user_name'], ENT_QUOTES, 'UTF-8');
                        }
                    }
                    $actionLabels = [
                        'created' => '設定',
                        'changed' => '変更',
                        'revoked' => '解除',
                        'expired' => '期限切れ',
                    ];
                    $actionLabel = $actionLabels[$h['action']] ?? $h['action'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(date('Y/m/d H:i', strtotime($h['logged_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(getRestrictionTypeLabel($h['restriction_type']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $h['reason'] !== null ? nl2br(htmlspecialchars($h['reason'], ENT_QUOTES, 'UTF-8')) : '－' ?></td>
                        <td><?= $adminName ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</main>

<?php require '../../../meetory-footer.php'; ?>
