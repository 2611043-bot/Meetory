<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) or (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/reports/admin/index.php');
    header('Location: ../../user/login.php?return_url=' . urlencode($return));
    exit;
}

if (!isset($_SESSION['role']) or $_SESSION['role'] !== 'admin') {
    exit('このページを閲覧する権限がありません。');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = getPDO();

$status_filter = $_GET['status'] ?? '';
if (!is_string($status_filter) or !isValidReportStatus($status_filter)) {
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
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
/* ページ全体の背景（Meetory標準の淡いブルーグラデーション） */
html:has(.report-page.report-admin),
body:has(.report-page.report-admin) {
    background: linear-gradient(135deg, #e6f1fc 0%, #f4f8fc 52%, #ebf3fb 100%) !important;
    background-attachment: fixed !important;
}

.report-page.report-admin {
    background: transparent !important;
    padding: 24px 18px 110px;
}

.report-page.report-admin .admin-layout {
    max-width: 1140px;
    margin: 0 auto;
    gap: 22px;
    align-items: flex-start;
    background: transparent !important;
}

/* 右側メインエリア（左の管理メニューと揃えた白のフローティングカード） */
.report-page.report-admin .admin-main-content {
    flex: 1;
    min-width: 0;
    padding: 28px 32px;
    border: 1px solid rgba(25, 118, 210, 0.12);
    border-radius: 20px;
    background-color: rgba(255, 255, 255, 0.96) !important;
    box-shadow: 0 10px 30px -8px rgba(15, 41, 66, 0.08);
    box-sizing: border-box;
}

/* 見出し */
.report-page.report-admin .admin-main-content > h1 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 20px;
    font-size: 22px;
    font-weight: 800;
    color: #0f2942;
    letter-spacing: -0.01em;
}

.report-page.report-admin .admin-main-content > h1::before {
    content: "";
    display: inline-block;
    width: 5px;
    height: 22px;
    border-radius: 999px;
    background-color: #1976d2;
}

/* 絞り込みフィルターフォーム */
.report-page.report-admin .report-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 12px;
    margin: 0 0 20px;
    padding: 14px 18px;
    border: 1px solid #e2ebf5;
    border-radius: 14px;
    background-color: #f8fbff;
}

.report-page.report-admin .report-filter label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.report-page.report-admin .report-filter select {
    min-width: 160px;
    height: 38px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background-color: #ffffff;
    font-size: 13px;
    color: #0f172a;
    outline: none;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.report-page.report-admin .report-filter select:focus {
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.14);
}

.report-page.report-admin .report-filter button[type="submit"] {
    height: 38px;
    padding: 0 20px;
    border: none;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(25, 118, 210, 0.22);
    transition: background-color 0.15s;
}

.report-page.report-admin .report-filter button[type="submit"]:hover {
    background-color: #1565c0;
}

/* テーブルデザイン */
.report-page.report-admin .report-list {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #e2ebf5;
    border-radius: 14px;
    overflow: hidden;
    background-color: #ffffff;
}

.report-page.report-admin .report-list thead th {
    padding: 13px 14px;
    border: none;
    border-bottom: 1px solid #dce6f2;
    background-color: #f1f7fe;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.report-page.report-admin .report-list tbody td {
    padding: 14px;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    font-size: 13px;
    vertical-align: middle;
}

.report-page.report-admin .report-list tbody tr:last-child td {
    border-bottom: none;
}

.report-page.report-admin .report-list tbody tr:hover td {
    background-color: #f8fbff;
}

/* 通報対象リンク */
.report-page.report-admin .report-list td:nth-child(3) a {
    color: #1976d2;
    font-weight: 700;
    text-decoration: none;
}

.report-page.report-admin .report-list td:nth-child(3) a:hover {
    text-decoration: underline;
}

/* 対応状況バッジ */
.report-page.report-admin .report-list td:nth-child(7) span {
    display: inline-block;
    padding: 3px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

/* 詳細ボタン */
.report-page.report-admin .report-list td:last-child {
    white-space: nowrap;
}

.report-page.report-admin .report-list td:last-child a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 14px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background-color: #eff6ff;
    color: #1565c0;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
}

.report-page.report-admin .report-list td:last-child a:hover {
    background-color: #1976d2;
    border-color: #1976d2;
    color: #ffffff;
}

/* スマホ・タブレット対応 */
@media (max-width: 768px) {
    .report-page.report-admin .admin-main-content {
        padding: 20px 16px;
        border-radius: 16px;
    }

    .report-page.report-admin .report-list {
        display: block;
        overflow-x: auto;
    }
}
</style>

<main class="report-page report-admin">
    <div class="admin-layout">
        <?php require __DIR__ . '/../../admin/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

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
                                <?= htmlspecialchars(getReportStatusLabel($report['status'], ENT_QUOTES, 'UTF-8')) ?>
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

        </div>
    </div>
</main>

<?php require '../../../meetory-footer.php'; ?>