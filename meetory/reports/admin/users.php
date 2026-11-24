<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/reports/admin/users.php');
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

// フラッシュメッセージ
$flash = $_SESSION['restriction_flash'] ?? null;
unset($_SESSION['restriction_flash']);

// ユーザー検索
$keyword = $_GET['keyword'] ?? '';
if (!is_string($keyword)) {
    $keyword = '';
}
$keyword = trim($keyword);

$users = searchRestrictedUsers($pdo, $keyword);

require '../../../meetory-header.php';
?>

<link rel="stylesheet" href="../../assets/css/reports.css">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
.restriction-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.85rem;
    color: #fff;
}
.restriction-badge.suspend { background: #dc2626; }
.restriction-badge.listing { background: #ca8a04; }
.restriction-badge.purchase { background: #0ea5e9; }
.restriction-badge.message { background: #8b5cf6; }
.restriction-none { color: #666; font-size: 0.85rem; }
</style>

<main class="report-page report-admin">
    <div class="admin-layout">
        <?php require __DIR__ . '/../../admin/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1>ユーザー制限管理</h1>

    <?php if ($flash !== null): ?>
        <p class="report-flash" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form action="users.php" method="get" class="report-filter" style="margin-bottom: 1rem;">
        <label for="keyword">ユーザー名で検索</label>
        <input
            type="text"
            id="keyword"
            name="keyword"
            value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="ユーザー名を入力"
        >
        <button type="submit">検索</button>
    </form>

    <?php if (empty($users)): ?>
        <p>該当するユーザーはありません。</p>
    <?php else: ?>
        <table class="report-list">
            <thead>
                <tr>
                    <th>ユーザーID</th>
                    <th>ユーザー名</th>
                    <th>役割</th>
                    <th>登録日時</th>
                    <th>現在の制限</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $states = [];
                    $raw = $u['restriction_states'] ?? '';
                    if ($raw !== '' && $raw !== null) {
                        foreach (explode(',', $raw) as $pair) {
                            $pair = trim($pair);
                            if ($pair === '') {
                                continue;
                            }
                            list($type, $status) = explode(':', $pair, 2);
                            $states[] = $type;
                        }
                    }
                    $suspended = in_array('suspend', $states, true);
                    ?>
                    <tr>
                        <td><?= (int) $u['user_id'] ?></td>
                        <td>
                            <a href="restriction-form.php?user_id=<?= (int) $u['user_id'] ?>">
                                <?= htmlspecialchars($u['user_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('Y/m/d H:i', strtotime($u['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (empty($states)): ?>
                                <span class="restriction-none">なし</span>
                            <?php else: ?>
                                <?php foreach ($states as $type): ?>
                                    <span
                                        class="restriction-badge <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                                        title="<?= htmlspecialchars(getRestrictionTypeLabel($type), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <?= htmlspecialchars(getRestrictionTypeLabel($type), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a
                                href="restriction-form.php?user_id=<?= (int) $u['user_id'] ?>"
                                class="sidebar-link"
                            >
                                制限管理
                            </a>
                            <br>
                            <a
                                href="restriction-history.php?user_id=<?= (int) $u['user_id'] ?>"
                            >
                                履歴
                            </a>
                            <?php if ($suspended): ?>
                                <br>
                                <span style="color:#dc2626;font-size:0.8rem;">停止中</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p>
        <a href="../report/admin/index.php">通報管理に戻る</a>
    </p>

        </div>
    </div>
</main>

<?php require '../../../meetory-footer.php'; ?>
