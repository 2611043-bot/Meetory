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
<style>
/* ページ全体の背景（Meetory標準の淡いブルーグラデーション） */
html:has(.admin-container),
body:has(.admin-container) {
    background: linear-gradient(135deg, #e6f1fc 0%, #f4f8fc 52%, #ebf3fb 100%) !important;
    background-attachment: fixed !important;
}

.main-container.admin-container {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
    max-width: 1140px;
    margin: 0 auto;
    padding: 24px 18px 110px;
}

.main-container.admin-container .admin-layout {
    max-width: 1140px;
    margin: 0 auto;
    gap: 22px;
    align-items: flex-start;
    background: transparent !important;
}

/* 右側メインエリア（左の管理メニューと揃えた白のフローティングカード） */
.main-container.admin-container .admin-main-content {
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
.main-container.admin-container .admin-page-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 18px;
    font-size: 22px;
    font-weight: 800;
    color: #0f2942;
    letter-spacing: -0.01em;
}

.main-container.admin-container .admin-page-title::before {
    content: "";
    display: inline-block;
    width: 5px;
    height: 22px;
    border-radius: 999px;
    background-color: #1976d2;
}

/* 今すぐ更新・ステータスバー */
.main-container.admin-container .auto-update-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 12px;
    margin: 0 0 16px;
}

.main-container.admin-container .auto-refresh-button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 18px;
    border: none;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(25, 118, 210, 0.22);
    transition: background-color 0.15s, transform 0.1s;
}

.main-container.admin-container .auto-refresh-button:hover {
    background-color: #1565c0;
}

.main-container.admin-container .auto-refresh-button:active {
    transform: translateY(1px);
}

.main-container.admin-container .last-updated:not(:empty),
.main-container.admin-container .auto-update-status:not(:empty) {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #f8fbff;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
}

/* パンくずリスト */
.main-container.admin-container .admin-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 24px;
    padding: 6px 14px;
    border: 1px solid #e2ebf5;
    border-radius: 999px;
    background-color: #f8fbff;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}

.main-container.admin-container .admin-breadcrumb a {
    color: #1976d2;
    text-decoration: none;
}

.main-container.admin-container .admin-breadcrumb a:hover {
    text-decoration: underline;
}

/* 統計カードグリッド（4枚を美しく整列） */
.main-container.admin-container .admin-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin: 0 0 28px;
}

.main-container.admin-container .admin-stat-card {
    position: relative;
    padding: 18px 20px;
    border: 1px solid #e2ebf5;
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    box-shadow: 0 4px 14px rgba(15, 41, 66, 0.04);
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
}

.main-container.admin-container .admin-stat-card:hover {
    transform: translateY(-2px);
    border-color: #bfdbfe;
    box-shadow: 0 8px 20px rgba(25, 118, 210, 0.08);
}

.main-container.admin-container .admin-stat-title {
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}

.main-container.admin-container .admin-stat-value {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    color: #0f2942;
    line-height: 1.15;
    letter-spacing: -0.02em;
}

/* クイックアクセスセクション */
.main-container.admin-container .admin-section-title {
    margin: 0 0 14px;
    font-size: 16px;
    font-weight: 800;
    color: #0f2942;
}

.main-container.admin-container .admin-shortcut-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.main-container.admin-container .admin-shortcut-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border: 1px solid #dce6f2;
    border-radius: 14px;
    background-color: #f8fbff;
    color: #0f2942;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s, transform 0.15s, box-shadow 0.15s;
}

.main-container.admin-container .admin-shortcut-card i {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background-color: #e3f2fd;
    color: #1976d2;
    font-size: 15px;
    flex-shrink: 0;
    transition: background-color 0.15s, color 0.15s;
}

.main-container.admin-container .admin-shortcut-card:hover {
    background-color: #ffffff;
    border-color: #90caf9;
    color: #1976d2;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(25, 118, 210, 0.1);
}

.main-container.admin-container .admin-shortcut-card:hover i {
    background-color: #1976d2;
    color: #ffffff;
}

/* タブレット・スマホ対応 */
@media (max-width: 960px) {
    .main-container.admin-container .admin-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .main-container.admin-container {
        padding: 14px 10px 100px;
    }

    .main-container.admin-container .admin-main-content {
        padding: 20px 16px;
        border-radius: 16px;
    }

    .main-container.admin-container .admin-shortcut-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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

    <!-- ユーザー登録推移（折れ線グラフ） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">ユーザー登録推移</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="userRegistrationChart"></canvas>
        </div>
    </section>

    <!-- 商品登録推移（棒グラフ） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">商品登録推移</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="goodsListingChart"></canvas>
        </div>
    </section>

        </div>
    </div>
</main>

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

<?php require '../../meetory-footer.php'; ?>

<script src="../assets/js/admin.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin.js') ?>" defer></script>