<?php

// 管理者認証チェック（直接アクセス防止）
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['user_id']) ||
    (int) $_SESSION['user_id'] <= 0 ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    return;
}

// 現在のURLを取得（アクティブメニュー判定用）
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

$isDashboard = strpos($currentUri, '/admin/index.php') !== false || strpos($currentUri, '/admin/dashboard.php') !== false;
$isAnalysis  = strpos($currentUri, '/admin/analysis.php') !== false;
$isReports   = strpos($currentUri, '/reports/admin/index.php') !== false;
$isUsers     = strpos($currentUri, '/reports/admin/users.php') !== false;
$isInquiries = strpos($currentUri, '/reports/admin/inquiries.php') !== false;

// 管理情報パネルのデータ取得
$pendingReports = 0;
$pendingInquiries = 0;
$recentLogs = [];

try {
    if (!isset($pdo)) {
        $pdo = getPDO();
    }

    // 未対応の報告件数（status = 'pending'）
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE status = :status');
    $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
    $stmt->execute();
    $pendingReports = (int) $stmt->fetchColumn();

    // 未対応のお問い合わせ件数（status = '未対応'）
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE status = :status');
    $stmt->bindValue(':status', '未対応', PDO::PARAM_STR);
    $stmt->execute();
    $pendingInquiries = (int) $stmt->fetchColumn();

    // 最近の管理操作（最新5件）
    $stmt = $pdo->prepare('
        SELECT action_type, created_at
        FROM admin_operation_logs
        ORDER BY created_at DESC
        LIMIT 5
    ');
    $stmt->execute();
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('admin sidebar info panel error: ' . $e->getMessage());
}
?>

<aside class="admin-sidebar">
    <div class="admin-sidebar-brand">
        <div class="admin-sidebar-brand-icon">
            <i class="fa-solid fa-store"></i>
        </div>
        <div class="admin-sidebar-brand-text">
            <span class="admin-sidebar-brand-name">MEETORY</span>
            <span class="admin-sidebar-brand-sub">ADMIN</span>
        </div>
    </div>

    <nav class="admin-sidebar-nav">
        <!-- カテゴリ: 基本管理 -->
        <div class="admin-sidebar-category">
            <div class="admin-sidebar-category-header">
                <span class="admin-sidebar-category-icon">
                    <i class="fa-solid fa-layer-group"></i>
                </span>
                <span class="admin-sidebar-category-title">基本管理</span>
            </div>
            <ul class="admin-sidebar-category-list">
                <li>
                    <a
                        href="/Meetory/meetory/admin/index.php"
                        class="admin-sidebar-link<?= $isDashboard ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>ダッシュボード</span>
                    </a>
                </li>
                <li>
                    <a
                        href="/Meetory/meetory/reports/admin/users.php"
                        class="admin-sidebar-link<?= $isUsers ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-users"></i>
                        <span>ユーザー管理</span>
                    </a>
                </li>
                <li>
                    <a
                        href="/Meetory/meetory/admin/goods-list.php"
                        class="admin-sidebar-link<?= (strpos($currentUri, '/admin/goods-list.php') !== false || strpos($currentUri, '/admin/goods-detail.php') !== false) ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-box"></i>
                        <span>商品管理</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="admin-sidebar-link disabled">
                        <i class="fa-solid fa-credit-card"></i>
                        <span>取引管理</span>
                        <span class="admin-sidebar-badge">準備中</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="admin-sidebar-link disabled">
                        <i class="fa-solid fa-dollar-sign"></i>
                        <span>売上・収益管理</span>
                        <span class="admin-sidebar-badge">準備中</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- カテゴリ: サイト運営 -->
        <div class="admin-sidebar-category">
            <div class="admin-sidebar-category-header">
                <span class="admin-sidebar-category-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </span>
                <span class="admin-sidebar-category-title">サイト運営</span>
            </div>
            <ul class="admin-sidebar-category-list">
                <li>
                    <a
                        href="/Meetory/meetory/reports/admin/index.php"
                        class="admin-sidebar-link<?= $isReports ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-flag"></i>
                        <span>通報・違反管理</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="admin-sidebar-link disabled">
                        <i class="fa-solid fa-comment-dots"></i>
                        <span>レビュー管理</span>
                        <span class="admin-sidebar-badge">準備中</span>
                    </a>
                </li>
                <li>
                    <a
                        href="/Meetory/meetory/reports/admin/inquiries.php"
                        class="admin-sidebar-link<?= $isInquiries ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-envelope"></i>
                        <span>お問い合わせ管理</span>
                    </a>
                </li>
                <li>
                    <a
                        href="/Meetory/meetory/admin/operation-logs.php"
                        class="admin-sidebar-link<?= strpos($currentUri, '/admin/operation-logs.php') !== false ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-clipboard-list"></i>
                        <span>操作ログ一覧</span>
                    </a>
                </li>
                <li>
                    <a
                        href="/Meetory/meetory/admin/operation-log-summary.php"
                        class="admin-sidebar-link<?= strpos($currentUri, '/admin/operation-log-summary.php') !== false ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>操作ログ集計</span>
                    </a>
                </li>
                <li>
                    <a
                        href="/Meetory/meetory/admin/operation-log-detail.php"
                        class="admin-sidebar-link<?= strpos($currentUri, '/admin/operation-log-detail.php') !== false ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-file-circle-info"></i>
                        <span>操作ログ詳細</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="admin-sidebar-link disabled">
                        <i class="fa-solid fa-bullhorn"></i>
                        <span>お知らせ管理</span>
                        <span class="admin-sidebar-badge">準備中</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- カテゴリ: 分析・設定 -->
        <div class="admin-sidebar-category">
            <div class="admin-sidebar-category-header">
                <span class="admin-sidebar-category-icon">
                    <i class="fa-solid fa-sliders"></i>
                </span>
                <span class="admin-sidebar-category-title">分析・設定</span>
            </div>
            <ul class="admin-sidebar-category-list">
                <li>
                    <a
                        href="/Meetory/meetory/admin/analysis.php"
                        class="admin-sidebar-link<?= $isAnalysis ? ' active' : '' ?>"
                    >
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>分析・統計</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="admin-sidebar-link disabled">
                        <i class="fa-solid fa-robot"></i>
                        <span>AI利用状況</span>
                        <span class="admin-sidebar-badge">準備中</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="admin-sidebar-link disabled">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>検索・閲覧分析</span>
                        <span class="admin-sidebar-badge">準備中</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="admin-sidebar-info">
        <h3 class="admin-sidebar-info-title">管理情報</h3>
        <ul class="admin-sidebar-info-list">
            <li class="admin-sidebar-info-item">
                <span class="admin-sidebar-info-label">未対応の報告</span>
                <span class="admin-sidebar-info-value"><?= (int) $pendingReports ?></span>
            </li>
            <li class="admin-sidebar-info-item">
                <span class="admin-sidebar-info-label">未対応のお問い合わせ</span>
                <span class="admin-sidebar-info-value"><?= (int) $pendingInquiries ?></span>
            </li>
        </ul>
        <?php if (!empty($recentLogs)): ?>
        <div class="admin-sidebar-recent-logs">
            <h4 class="admin-sidebar-recent-logs-title">最近の操作</h4>
            <ul class="admin-sidebar-recent-logs-list">
                <?php foreach ($recentLogs as $log): ?>
                <li class="admin-sidebar-recent-log-item">
                    <span class="admin-sidebar-recent-log-action"><?= htmlspecialchars($log['action_type'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="admin-sidebar-recent-log-time"><?= htmlspecialchars(date('m/d H:i', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</aside>
