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

$report_id = filter_input(INPUT_GET, 'report_id', FILTER_VALIDATE_INT);
if ($report_id === false || $report_id === null || $report_id <= 0) {
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

<main class="report-page report-admin">

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
                <?= htmlspecialchars(getReportStatusLabel($report['status']), ENT_QUOTES, 'UTF-8') ?>
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

</main>

<?php require '../../../meetory-footer.php'; ?>