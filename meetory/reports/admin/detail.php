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

$report_id = filter_input(INPUT_GET, 'report_id', FILTER_VALIDATE_INT);
if ($report_id === false or $report_id === null or $report_id <= 0) {
    exit('通報IDが正しくありません。');
}

$stmt = $pdo->prepare(
    'SELECT
        r.report_id,
        r.reporter_id,
        r.target_type,
        r.target_id,
        r.reason,
        r.description,
        r.status,
        r.created_at,
        r.updated_at,
        reporter.user_name AS reporter_name,
        reporter.created_at AS reporter_created_at
     FROM reports AS r
     INNER JOIN users AS reporter
        ON reporter.user_id = r.reporter_id
     WHERE r.report_id = ?
     LIMIT 1'
);
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if ($report === false) {
    exit('通報が見つかりません。');
}

$target_type = $report['target_type'];
$target_id   = (int) $report['target_id'];

$target = null;
if ($target_type === 'goods') {
    $stmt = $pdo->prepare(
        'SELECT
            g.goods_id,
            g.goods_name,
            g.price,
            g.is_sold,
            g.seller_id,
            seller.user_name AS seller_name
         FROM goods AS g
         INNER JOIN users AS seller
            ON seller.user_id = g.seller_id
         WHERE g.goods_id = ?
         LIMIT 1'
    );
    $stmt->execute([$target_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $target = $row !== false ? $row : null;
} elseif ($target_type === 'user') {
    $stmt = $pdo->prepare(
        'SELECT user_id, user_name, created_at
         FROM users
         WHERE user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$target_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $target = $row !== false ? $row : null;
}

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
    color: #0f172a;
}

/* 大見出し */
.report-page.report-admin .admin-main-content > h1 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 22px;
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

/* フラッシュメッセージ */
.report-page.report-admin .report-flash {
    margin: 0 0 20px;
    padding: 12px 16px;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    background-color: #f0f9ff;
    color: #0369a1;
    font-size: 13px;
    font-weight: 600;
}

/* 通報詳細リスト（dl / dt / dd をすっきりした定義グリッドに整形） */
.report-page.report-admin .report-detail {
    display: grid;
    grid-template-columns: 170px minmax(0, 1fr);
    margin: 0 0 28px;
    border: 1px solid #e2ebf5;
    border-radius: 16px;
    overflow: hidden;
    background-color: #ffffff;
}

.report-page.report-admin .report-detail dt,
.report-page.report-admin .report-detail dd {
    margin: 0;
    padding: 14px 18px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    line-height: 1.65;
    box-sizing: border-box;
}

.report-page.report-admin .report-detail dt {
    background-color: #f4f8fd;
    color: #334155;
    font-weight: 700;
    border-right: 1px solid #e2ebf5;
}

.report-page.report-admin .report-detail dd {
    color: #0f172a;
    overflow-wrap: anywhere;
}

.report-page.report-admin .report-detail dt:last-of-type,
.report-page.report-admin .report-detail dd:last-of-type {
    border-bottom: none;
}

/* 通報対象リンク・補足メタ情報 */
.report-page.report-admin .report-detail dd a {
    color: #1976d2;
    font-weight: 700;
    text-decoration: none;
}

.report-page.report-admin .report-detail dd a:hover {
    text-decoration: underline;
}

.report-page.report-admin .report-target-meta {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 10px;
    border-radius: 999px;
    background-color: #f1f5f9;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

/* 対応状況バッジ */
.report-page.report-admin .report-detail dd span[class^="report-status-"] {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

/* 中見出し（対応状況の変更） */
.report-page.report-admin .admin-main-content > h2 {
    margin: 0 0 14px;
    font-size: 16px;
    font-weight: 800;
    color: #0f2942;
}

/* 対応状況変更フォームカード */
.report-page.report-admin .report-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px 18px;
    margin: 0 0 24px;
    padding: 18px 20px;
    border: 1px solid #e2ebf5;
    border-radius: 16px;
    background-color: #f8fbff;
}

.report-page.report-admin .report-form p {
    margin: 0;
}

.report-page.report-admin .report-form label {
    display: inline-block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}

.report-page.report-admin .report-form select {
    min-width: 190px;
    height: 40px;
    padding: 0 16px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background-color: #ffffff;
    font-size: 13px;
    color: #0f172a;
    outline: none;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.report-page.report-admin .report-form select:focus {
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.14);
}

.report-page.report-admin .report-form button[type="submit"] {
    height: 40px;
    padding: 0 24px;
    border: none;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(25, 118, 210, 0.22);
    transition: background-color 0.15s;
}

.report-page.report-admin .report-form button[type="submit"]:hover {
    background-color: #1565c0;
}

/* 下部「通報一覧に戻る」リンク */
.report-page.report-admin .admin-main-content > p:last-child {
    margin: 0;
}

.report-page.report-admin .admin-main-content > p:last-child a {
    display: inline-flex;
    align-items: center;
    padding: 8px 18px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #f8fbff;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, color 0.15s;
}

.report-page.report-admin .admin-main-content > p:last-child a:hover {
    background-color: #e8f3fe;
    color: #1976d2;
}

/* スマホ・タブレット対応 */
@media (max-width: 768px) {
    .report-page.report-admin .admin-main-content {
        padding: 20px 16px;
        border-radius: 16px;
    }

    .report-page.report-admin .report-detail {
        grid-template-columns: 1fr;
    }

    .report-page.report-admin .report-detail dt {
        border-right: none;
        border-bottom: none;
        padding-bottom: 4px;
    }

    .report-page.report-admin .report-detail dd {
        padding-top: 6px;
    }

    .report-page.report-admin .report-target-meta {
        display: block;
        margin: 6px 0 0;
        border-radius: 10px;
    }
}
</style>

<main class="report-page report-admin">
    <div class="admin-layout">
        <?php require __DIR__ . '/../../admin/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1>通報詳細</h1>

    <?php if (!empty($_SESSION['report_status_flash'])): ?>
        <p class="report-flash" role="status">
            <?= htmlspecialchars($_SESSION['report_status_flash'], ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php unset($_SESSION['report_status_flash']); ?>
    <?php endif; ?>

    <dl class="report-detail">
        <dt>通報ID</dt>
        <dd><?= (int) $report['report_id'] ?></dd>

        <dt>通報対象の種類</dt>
        <dd><?= htmlspecialchars(getReportTargetTypeLabel($target_type), ENT_QUOTES, 'UTF-8') ?></dd>

        <dt>通報対象</dt>
        <dd>
            <?php if ($target !== null): ?>
                <?php if ($target_type === 'goods'): ?>
                    <a href="../../goods/goods-detail.php?goods_id=<?= $target_id ?>">
                        <?= htmlspecialchars($target['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <span class="report-target-meta">
                        価格：<?= number_format((int) $target['price']) ?>円 /
                        出品者：<?= htmlspecialchars($target['seller_name'], ENT_QUOTES, 'UTF-8') ?> /
                        <?= (int) $target['is_sold'] === 1 ? '売却済み' : '出品中' ?>
                    </span>
                <?php else: ?>
                    <a href="../../user/profile.php?user_id=<?= $target_id ?>">
                        <?= htmlspecialchars($target['user_name'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <span class="report-target-meta">
                        会員登録日：<?= htmlspecialchars(date('Y年m月d日', strtotime($target['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            <?php else: ?>
                (削除済み、または対象が見つかりません)
            <?php endif; ?>
        </dd>

        <dt>通報理由</dt>
        <dd><?= htmlspecialchars($report['reason'], ENT_QUOTES, 'UTF-8') ?></dd>

        <dt>詳細説明</dt>
        <dd>
            <?php if ($report['description'] !== null && $report['description'] !== ''): ?>
                <?= nl2br(htmlspecialchars($report['description'], ENT_QUOTES, 'UTF-8')) ?>
            <?php else: ?>
                なし
            <?php endif; ?>
        </dd>

        <dt>通報者</dt>
        <dd>
            <?= htmlspecialchars($report['reporter_name'], ENT_QUOTES, 'UTF-8') ?>
            <span class="report-target-meta">
                (ID: <?= (int) $report['reporter_id'] ?> /
                登録日：<?= htmlspecialchars(date('Y年m月d日', strtotime($report['reporter_created_at'])), ENT_QUOTES, 'UTF-8') ?>)
            </span>
        </dd>

        <dt>通報日時</dt>
        <dd><?= htmlspecialchars($report['created_at'], ENT_QUOTES, 'UTF-8') ?></dd>

        <dt>対応状況</dt>
        <dd>
            <?php $status_class = 'report-status-' . htmlspecialchars($report['status'], ENT_QUOTES, 'UTF-8'); ?>
            <span class="<?= $status_class ?>">
                <?= htmlspecialchars(getReportStatusLabel($report['status'], ENT_QUOTES, 'UTF-8')) ?>
            </span>
        </dd>

        <dt>最終更新日時</dt>
        <dd><?= htmlspecialchars($report['updated_at'], ENT_QUOTES, 'UTF-8') ?></dd>
    </dl>

    <h2>対応状況の変更</h2>

    <form action="update-status.php" method="post" class="report-form">

        <input type="hidden" name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="report_id" value="<?= (int) $report['report_id'] ?>">

        <p>
            <label for="status">対応状況</label><br>
            <select id="status" name="status">
                <?php foreach (getReportStatuses() as $status_value => $status_label): ?>
                    <option value="<?= htmlspecialchars($status_value, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $report['status'] === $status_value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <button type="submit">変更を保存</button>

    </form>

    <p>
        <a href="index.php">通報一覧に戻る</a>
    </p>

        </div>
    </div>
</main>

<?php require '../../../meetory-footer.php'; ?>