<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

$pdo = getPDO();

// 統計情報をDBから取得（集計処理はHTML出力より前に実行）
$stats = [
    'total_users'     => 0,
    'total_goods'     => 0,
    'total_orders'    => 0,
    'completed_orders'=> 0,
];

try {
    // 総ユーザー数
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
    $stmt->execute();
    $stats['total_users'] = (int) $stmt->fetchColumn();

    // 総商品数
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM goods');
    $stmt->execute();
    $stats['total_goods'] = (int) $stmt->fetchColumn();

    // 総取引数
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM buy');
    $stmt->execute();
    $stats['total_orders'] = (int) $stmt->fetchColumn();

    // 取引完了数（transaction_status = '取引完了'）
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM buy WHERE transaction_status = :status');
    $stmt->bindValue(':status', '取引完了', PDO::PARAM_STR);
    $stmt->execute();
    $stats['completed_orders'] = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    // DBエラー時は詳細情報を表示せず、0のまま維持
    error_log('admin dashboard stats error: ' . $e->getMessage());
}

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

<main class="main-container admin-container">
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1 class="admin-page-title">管理ダッシュボード</h1>

    <div class="auto-update-controls">
        <button id="autoRefreshButton" class="auto-refresh-button">今すぐ更新</button>
        <span id="lastUpdated" class="last-updated"></span>
        <span id="autoRefreshStatus" class="auto-update-status"></span>
    </div>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>ダッシュボード</span>
    </nav>

    <!-- 統計カード -->
    <section class="admin-stats">
        <div class="admin-stat-card">
            <h2 class="admin-stat-title">総ユーザー数</h2>
            <p class="admin-stat-value"><?= htmlspecialchars($stats['total_users'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="admin-stat-card">
            <h2 class="admin-stat-title">総商品数</h2>
            <p class="admin-stat-value"><?= htmlspecialchars($stats['total_goods'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="admin-stat-card">
            <h2 class="admin-stat-title">総取引数</h2>
            <p class="admin-stat-value"><?= htmlspecialchars($stats['total_orders'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="admin-stat-card">
            <h2 class="admin-stat-title">取引完了数</h2>
            <p class="admin-stat-value"><?= htmlspecialchars($stats['completed_orders'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </section>

    <!-- ショートカットメニュー -->
    <section class="admin-shortcuts">
        <h2 class="admin-section-title">クイックアクセス</h2>
        <div class="admin-shortcut-grid">
            <a href="../reports/admin/users.php" class="admin-shortcut-card">
                <i class="fa-solid fa-user-shield"></i>
                <span>ユーザー制限管理</span>
            </a>
            <a href="../reports/admin/index.php" class="admin-shortcut-card">
                <i class="fa-solid fa-flag"></i>
                <span>通報管理</span>
            </a>
        </div>
    </section>

        </div>
    </div>
</main>

<?php require '../../meetory-footer.php'; ?>

<script src="../assets/js/admin.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin.js') ?>" defer></script>
