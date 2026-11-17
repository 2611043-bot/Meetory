<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/reports/admin/index.php');
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

$status_filter = $_GET['status'] ?? '';
if (!is_string($status_filter) || !isValidReportStatus($status_filter)) {
    $status_filter = '';
}

$sql = '
    SELECT
        r.report_id,
        r.reporter_id,
        r.target_type,
        r.target_id,
        r.reason,
        r.status,
        r.created_at,
        reporter.user_name AS reporter_name,
        CASE r.target_type
            WHEN \'goods\' THEN (SELECT goods_name FROM goods WHERE goods_id = r.target_id)
            WHEN \'user\'  THEN (SELECT user_name FROM users WHERE user_id = r.target_id)
            ELSE NULL
        END AS target_name
    FROM reports AS r
    INNER JOIN users AS reporter
        ON reporter.user_id = r.reporter_id
    WHERE 1 = 1';

$params = [];
if ($status_filter !== '') {
    $sql .= ' AND r.status = :status';
    $params[':status'] = $status_filter;
}

$sql .= ' ORDER BY r.created_at DESC, r.report_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

require '../../../meetory-header.php';
?>

<link rel="stylesheet" href="../../assets/css/reports.css">

<main class="report-page report-admin">

    <h1>通報一覧</h1>

    <form action="index.php" method="get" class="report-filter">
        <label for="status_filter">対応状況</label>
        <select id="status_filter" name="status">
            <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>すべて</option>
            <?php foreach (getReportStatuses() as $status_value => $status_label): ?>
                <option value="<?= htmlspecialchars($status_value, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $status_filter === $status_value ? 'selected' : '' ?>>
                    <?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">絞り込む</button>
    </form>

    <?php if (empty($reports)): ?>

        <p>該当する通報はありません。</p>

    <?php else: ?>

        <table class="report-list">
            <thead>
                <tr>
                    <th>通報ID</th>
                    <th>種別</th>
                    <th>通報対象</th>
                    <th>通報理由</th>
                    <th>通報者</th>
                    <th>通報日時</th>
                    <th>対応状況</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <?php
                    $target_type = $report['target_type'];
                    $target_id   = (int) $report['target_id'];
                    $target_name = $report['target_name'];
                    ?>
                    <tr>
                        <td><?= (int) $report['report_id'] ?></td>
                        <td><?= htmlspecialchars(getReportTargetTypeLabel($target_type), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($target_name !== null && $target_name !== ''): ?>
                                <?php if ($target_type === 'goods'): ?>
                                    <a href="../../goods/goods-detail.php?goods_id=<?= $target_id ?>">
                                        <?= htmlspecialchars($target_name, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php else: ?>
                                    <a href="../../user/profile.php?user_id=<?= $target_id ?>">
                                        <?= htmlspecialchars($target_name, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                (削除済み)
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($report['reason'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($report['reporter_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($report['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php $status_class = 'report-status-' . htmlspecialchars($report['status'], ENT_QUOTES, 'UTF-8'); ?>
                            <span class="<?= $status_class ?>">
                                <?= htmlspecialchars(getReportStatusLabel($report['status']), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <a href="detail.php?report_id=<?= (int) $report['report_id'] ?>">詳細</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

</main>

<?php require '../../../meetory-footer.php'; ?>