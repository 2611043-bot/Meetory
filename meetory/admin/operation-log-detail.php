<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

$pdo = getPDO();

// 操作種別ラベル（日本語表示用）
$action_type_labels = [
    'goods_delete' => '商品削除',
    'goods_publish_stop' => '商品公開停止',
    'goods_publish_resume' => '商品公開再開',
    'inquiry_reply' => 'お問い合わせ返信',
    'inquiry_status_change' => 'お問い合わせステータス変更',
    'restriction_create' => 'アカウント利用制限',
    'restriction_revoke' => 'アカウント利用制限解除',
    'report_status_change' => '違反報告ステータス変更',
];

// CSRFトークン
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// log_id を取得
$log_id = filter_input(INPUT_GET, 'log_id', FILTER_VALIDATE_INT);

if ($log_id === false || $log_id === null || $log_id <= 0) {
    // log_id が指定されていない場合は選択画面を表示
    require '../../meetory-header.php';
    ?>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

    <main class="main-container admin-container">
        <div class="admin-layout">
            <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
            <div class="admin-main-content">

    <h1 class="admin-page-title">操作ログ詳細</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>操作ログ</span>
        <span class="admin-breadcrumb-separator">›</span>
        <span>詳細</span>
    </nav>

    <div class="admin-summary-empty">
        <p>確認するログを選択してください。</p>
        <p>
            <a href="operation-logs.php" style="color: #1a3a5c; text-decoration: underline;">操作ログ一覧へ移動</a>
        </p>
    </div>

            </div>
        </div>
    </main>

    <?php require '../../meetory-footer.php'; ?>
    <?php
    exit;
}

// 対象ログを取得
$stmt = $pdo->prepare(
    'SELECT
        l.log_id,
        l.admin_id,
        u.user_name AS admin_name,
        l.action_type,
        l.target_type,
        l.target_id,
        l.details,
        l.ip_address,
        l.created_at
    FROM admin_operation_logs AS l
    LEFT JOIN users AS u ON u.user_id = l.admin_id
    WHERE l.log_id = ?'
);
$stmt->execute([$log_id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    // 存在しないログIDの場合
    require '../../meetory-header.php';
    ?>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

    <main class="main-container admin-container">
        <div class="admin-layout">
            <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
            <div class="admin-main-content">

    <h1 class="admin-page-title">操作ログ詳細</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>操作ログ</span>
        <span class="admin-breadcrumb-separator">›</span>
        <span>詳細</span>
    </nav>

    <div class="admin-summary-empty">
        <p>指定されたログは存在しません。</p>
        <p>
            <a href="operation-logs.php" style="color: #1a3a5c; text-decoration: underline;">操作ログ一覧へ移動</a>
        </p>
    </div>

            </div>
        </div>
    </main>

    <?php require '../../meetory-footer.php'; ?>
    <?php
    exit;
}

// エスケープ処理
$logId = (int) $log['log_id'];
$adminId = $log['admin_id'] !== null ? (int) $log['admin_id'] : '－';
$adminName = $log['admin_name'] !== null && $log['admin_name'] !== ''
    ? $escape($log['admin_name'])
    : '削除済みユーザー';
$actionType = $escape($action_type_labels[$log['action_type']] ?? $log['action_type']);
$actionTypeRaw = $escape($log['action_type']);
$targetType = $log['target_type'] !== null && $log['target_type'] !== ''
    ? $escape($log['target_type'])
    : '－';
$targetId = $log['target_id'] !== null ? (int) $log['target_id'] : '－';
$details = $log['details'] !== null && $log['details'] !== ''
    ? $escape($log['details'])
    : '－';
$ipAddress = $log['ip_address'] !== null && $log['ip_address'] !== ''
    ? $escape($log['ip_address'])
    : '－';
$createdAt = $escape(date('Y/m/d H:i', strtotime($log['created_at'])));

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
/* 操作ログ詳細専用スタイル */
.admin-operation-detail {
    max-width: 100%;
}

.admin-detail-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    margin: 0 0 24px;
}

.admin-detail-card h2 {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f1f5f9;
}

.admin-detail-item {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.admin-detail-item:last-child {
    border-bottom: none;
}

.admin-detail-label {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    min-width: 160px;
    flex-shrink: 0;
}

.admin-detail-value {
    font-size: 14px;
    color: #334155;
    word-break: break-word;
}

.admin-detail-value.ip {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 13px;
    color: #475569;
}

.admin-detail-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    background: #e2e8f0;
    color: #475569;
}

/* 戻るリンク */
.admin-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 24px;
    padding: 10px 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #ffffff;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
}

.admin-back-link:hover {
    background-color: #f1f5f9;
    color: #0f2942;
    border-color: #bfdbfe;
}

/* 空状態 */
.admin-summary-empty {
    padding: 40px 20px;
    text-align: center;
    color: #64748b;
    font-size: 15px;
}
</style>

<main class="main-container admin-container">
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1 class="admin-page-title">操作ログ詳細</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>操作ログ</span>
        <span class="admin-breadcrumb-separator">›</span>
        <span>詳細</span>
    </nav>

    <a href="operation-logs.php" class="admin-back-link">← 操作ログ一覧に戻る</a>

    <div class="admin-detail-card">
        <h2>ログ情報</h2>

        <div class="admin-detail-item">
            <span class="admin-detail-label">ログID</span>
            <span class="admin-detail-value"><?= $logId ?></span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">操作した管理者ID</span>
            <span class="admin-detail-value"><?= $adminId ?></span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">操作した管理者名</span>
            <span class="admin-detail-value"><?= $adminName ?></span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">操作種別</span>
            <span class="admin-detail-value">
                <span class="admin-detail-badge"><?= $actionType ?></span>
                <span style="margin-left: 8px; font-size: 12px; color: #64748b;">(<?= $actionTypeRaw ?>)</span>
            </span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">対象種別</span>
            <span class="admin-detail-value"><?= $targetType ?></span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">対象ID</span>
            <span class="admin-detail-value"><?= $targetId ?></span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">操作の詳細</span>
            <span class="admin-detail-value"><?= $details ?></span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">IPアドレス</span>
            <span class="admin-detail-value ip"><?= $ipAddress ?></span>
        </div>

        <div class="admin-detail-item">
            <span class="admin-detail-label">操作日時</span>
            <span class="admin-detail-value"><?= $createdAt ?></span>
        </div>
    </div>

        </div>
    </div>
</main>

<?php require '../../meetory-footer.php'; ?>
