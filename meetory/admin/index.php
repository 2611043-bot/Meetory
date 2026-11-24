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

// 直近6か月（当月を含む）の月ラベルと数値を初期化
$monthLabels = [];
$userData = [];
$goodsData = [];

for ($i = 5; $i >= 0; $i--) {
    $monthLabels[] = date('Y-m', strtotime("-$i months"));
    $userData[] = 0;
    $goodsData[] = 0;
}

// 集計期間の開始日（5か月前の月初）
$startDate = date('Y-m-01', strtotime('-5 months'));

try {
    // ユーザー登録推移（直近6か月）
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
        FROM users
        WHERE created_at >= :start_date
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->execute();
    $userResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($userResults as $row) {
        $month = $row['month'];
        $index = array_search($month, $monthLabels, true);
        if ($index !== false) {
            $userData[$index] = (int) $row['cnt'];
        }
    }

    // 商品出品推移（直近6か月）
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(listed_at, '%Y-%m') AS month, COUNT(*) AS cnt
        FROM goods
        WHERE listed_at >= :start_date
        GROUP BY DATE_FORMAT(listed_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->execute();
    $goodsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($goodsResults as $row) {
        $month = $row['month'];
        $index = array_search($month, $monthLabels, true);
        if ($index !== false) {
            $goodsData[$index] = (int) $row['cnt'];
        }
    }
} catch (Throwable $e) {
    error_log('admin dashboard chart error: ' . $e->getMessage());
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

    <!-- 簡易グラフ -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">ユーザー登録推移</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="userRegistrationChart"></canvas>
        </div>
    </section>

    <section class="admin-widget">
        <h2 class="admin-widget-title">商品出品推移</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="goodsListingChart"></canvas>
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
            <a href="analysis.php" class="admin-shortcut-card">
                <i class="fa-solid fa-chart-pie"></i>
                <span>統計・分析</span>
            </a>
        </div>
    </section>

        </div>
    </div>
</main>

<?php require '../../meetory-footer.php'; ?>

<script
    src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"
    defer
></script>
<script>
    const userChartLabels = <?= json_encode($monthLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const userChartData = <?= json_encode($userData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const goodsChartLabels = <?= json_encode($monthLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const goodsChartData = <?= json_encode($goodsData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../assets/js/admin.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin.js') ?>" defer></script>
