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

// 未対応件数の取得（サイドバーと同じ条件）
$pendingReports = 0;
$pendingInquiries = 0;

try {
    // 未対応の違反報告件数（status = 'pending'）
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE status = :status');
    $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
    $stmt->execute();
    $pendingReports = (int) $stmt->fetchColumn();

    // 未対応のお問い合わせ件数（status = '未対応'）
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE status = :status');
    $stmt->bindValue(':status', '未対応', PDO::PARAM_STR);
    $stmt->execute();
    $pendingInquiries = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    error_log('admin dashboard pending count error: ' . $e->getMessage());
}

// 今月・前月の実績・前月比を集計
$monthlyComparison = [
    'user_registrations'   => ['label' => '新規ユーザー登録数',   'table' => 'users',       'date_column' => 'created_at'],
    'goods_listings'       => ['label' => '商品出品数',           'table' => 'goods',       'date_column' => 'listed_at'],
    'purchases'            => ['label' => '購入件数',             'table' => 'buy',         'date_column' => 'purchased_at'],
    'inquiries'            => ['label' => 'お問い合わせ件数',     'table' => 'inquiries',   'date_column' => 'created_at'],
    'reports'              => ['label' => '通報件数',             'table' => 'reports',     'date_column' => 'created_at'],
];

// 今月の期間（月初から当日）
$this_month_start = date('Y-m-01');
$this_month_end   = date('Y-m-t 23:59:59');

// 前月の期間（先月月初から先月末）
$last_month_start = date('Y-m-01', strtotime('first day of last month'));
$last_month_end   = date('Y-m-t 23:59:59', strtotime('last day of last month'));

try {
    foreach ($monthlyComparison as $key => &$item) {
        $table   = $item['table'];
        $column  = $item['date_column'];

        // 今月の件数
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' >= :this_start AND ' . $column . ' <= :this_end'
        );
        $stmt->bindValue(':this_start', $this_month_start, PDO::PARAM_STR);
        $stmt->bindValue(':this_end',   $this_month_end,   PDO::PARAM_STR);
        $stmt->execute();
        $item['current'] = (int) $stmt->fetchColumn();

        // 前月の件数
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' >= :last_start AND ' . $column . ' <= :last_end'
        );
        $stmt->bindValue(':last_start', $last_month_start, PDO::PARAM_STR);
        $stmt->bindValue(':last_end',   $last_month_end,   PDO::PARAM_STR);
        $stmt->execute();
        $item['previous'] = (int) $stmt->fetchColumn();

        // 前月比計算
        if ($item['previous'] > 0) {
            $item['change_percent'] = round(($item['current'] - $item['previous']) / $item['previous'] * 100, 1);
            $item['direction'] = $item['current'] > $item['previous'] ? 'up' : ($item['current'] < $item['previous'] ? 'down' : 'flat');
        } else {
            $item['change_percent'] = null;
            $item['direction'] = $item['current'] > 0 ? 'up' : 'flat';
        }
    }
    unset($item);
} catch (Throwable $e) {
    error_log('admin dashboard monthly comparison error: ' . $e->getMessage());
}

// 最近の対応待ち案件一覧（違反報告とお問い合わせを統合、最大5件）
$pendingItems = [];

try {
    $stmt = $pdo->prepare("
        SELECT
            'report' AS item_type,
            '違反報告' AS item_label,
            r.report_id AS item_id,
            r.reason AS item_title,
            r.status AS item_status,
            r.created_at AS item_created_at
        FROM reports AS r
        WHERE r.status = :report_status

        UNION ALL

        SELECT
            'inquiry' AS item_type,
            'お問い合わせ' AS item_label,
            i.inquiry_id AS item_id,
            i.subject AS item_title,
            i.status AS item_status,
            i.created_at AS item_created_at
        FROM inquiries AS i
        WHERE i.status = :inquiry_status

        ORDER BY item_created_at DESC
        LIMIT 5
    ");
    $stmt->bindValue(':report_status', 'pending', PDO::PARAM_STR);
    $stmt->bindValue(':inquiry_status', '未対応', PDO::PARAM_STR);
    $stmt->execute();
    $pendingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('admin dashboard pending items error: ' . $e->getMessage());
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

/* 対応が必要な案件セクション */
.main-container.admin-container .admin-notices {
    margin: 0 0 28px;
}

.main-container.admin-container .admin-notice-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin: 0 0 16px;
}

.main-container.admin-container .admin-notice-card {
    padding: 16px 18px;
    border: 1px solid #e2ebf5;
    border-radius: 16px;
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.main-container.admin-container .admin-notice-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.main-container.admin-container .admin-notice-icon.report {
    background-color: #fef2f2;
    color: #dc2626;
}

.main-container.admin-container .admin-notice-icon.inquiry {
    background-color: #fef2f2;
    color: #b45309;
}

.main-container.admin-container .admin-notice-info {
    flex: 1;
}

.main-container.admin-container .admin-notice-label {
    margin: 0 0 4px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}

.main-container.admin-container .admin-notice-value {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #0f2942;
}

/* 対応待ち案件一覧 */
.main-container.admin-container .admin-notice-list {
    border: 1px solid #e2ebf5;
    border-radius: 16px;
    overflow: hidden;
    background: #ffffff;
}

.main-container.admin-container .admin-notice-list table {
    width: 100%;
    border-collapse: collapse;
}

.main-container.admin-container .admin-notice-list th {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    background-color: #f8fafc;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-align: left;
}

.main-container.admin-container .admin-notice-list td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
}

.main-container.admin-container .admin-notice-list tr:last-child td {
    border-bottom: none;
}

.main-container.admin-container .admin-notice-list tr:hover {
    background-color: #f8fafc;
}

.main-container.admin-container .admin-notice-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}

.main-container.admin-container .admin-notice-badge-report {
    background-color: #fef2f2;
    color: #dc2626;
}

.main-container.admin-container .admin-notice-badge-inquiry {
    background-color: #fff7ed;
    color: #b45309;
}

.main-container.admin-container .admin-notice-link {
    color: #1976d2;
    font-weight: 700;
    text-decoration: none;
    font-size: 12px;
}

.main-container.admin-container .admin-notice-link:hover {
    text-decoration: underline;
}

.main-container.admin-container .admin-notice-empty {
    padding: 24px 16px;
    text-align: center;
    color: #64748b;
    font-size: 14px;
    background: #f8fafc;
    border-radius: 12px;
}

/* 今月の実績・前月比セクション */
.main-container.admin-container .admin-monthly-comparison {
    margin: 0 0 28px;
}

.main-container.admin-container .admin-comparison-period {
    margin: 0 0 12px;
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
}

.main-container.admin-container .admin-comparison-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.main-container.admin-container .admin-comparison-table th {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-align: left;
}

.main-container.admin-container .admin-comparison-table td {
    border-bottom: 1px solid #f1f5f9;
    padding: 12px 16px;
    font-size: 13px;
    color: #334155;
}

.main-container.admin-container .admin-comparison-table tr:last-child td {
    border-bottom: none;
}

.main-container.admin-container .admin-comparison-table tr:hover {
    background: #f8fafc;
}

.main-container.admin-container .admin-change-up {
    color: #0f766e;
    font-weight: 700;
}

.main-container.admin-container .admin-change-down {
    color: #b42318;
    font-weight: 700;
}

.main-container.admin-container .admin-change-flat {
    color: #64748b;
}

.main-container.admin-container .admin-direction-up {
    color: #0f766e;
    font-weight: 700;
}

.main-container.admin-container .admin-direction-down {
    color: #b42318;
    font-weight: 700;
}

.main-container.admin-container .admin-direction-flat {
    color: #64748b;
}

@media (max-width: 768px) {
    .main-container.admin-container .admin-main-content {
        padding: 20px 16px;
        border-radius: 16px;
    }

    .main-container.admin-container .admin-notice-grid {
        grid-template-columns: 1fr;
    }
    .main-container.admin-container .admin-shortcut-grid {
        grid-template-columns: 1fr;
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

    <!-- 今月の実績・前月比 -->
    <section class="admin-monthly-comparison">
        <h2 class="admin-section-title">今月の実績・前月比</h2>
        <table class="admin-comparison-table">
            <thead>
                <tr>
                    <th>項目</th>
                    <th>今月</th>
                    <th>前月</th>
                    <th>前月比</th>
                    <th>増減</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthlyComparison as $key => $item): ?>
                    <?php
                    $label      = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                    $current    = (int) $item['current'];
                    $previous   = (int) $item['previous'];
                    $change     = $item['change_percent'];
                    $direction  = $item['direction'];

                    if ($change !== null) {
                        $changeText = $change >= 0 ? '+' . $change . '%' : $change . '%';
                        $changeClass = 'admin-change-' . $direction;
                    } else {
                        $changeText = '比較対象なし';
                        $changeClass = 'admin-change-flat';
                    }

                    $directionText = $direction === 'up' ? '増加' : ($direction === 'down' ? '減少' : '変化なし');
                    $directionClass = 'admin-direction-' . $direction;
                    ?>
                    <tr>
                        <td><?= $label ?></td>
                        <td><?= number_format($current) ?></td>
                        <td><?= number_format($previous) ?></td>
                        <td class="<?= $changeClass ?>"><?= $changeText ?></td>
                        <td class="<?= $directionClass ?>"><?= $directionText ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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

    <!-- 対応が必要な案件 -->
    <section class="admin-notices">
        <h2 class="admin-section-title">対応が必要な案件</h2>
        <div class="admin-notice-grid">
            <div class="admin-notice-card">
                <div class="admin-notice-icon report">
                    <i class="fa-solid fa-flag"></i>
                </div>
                <div class="admin-notice-info">
                    <p class="admin-notice-label">未対応の違反報告</p>
                    <p class="admin-notice-value"><?= (int) $pendingReports ?>件</p>
                </div>
            </div>
            <div class="admin-notice-card">
                <div class="admin-notice-icon inquiry">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div class="admin-notice-info">
                    <p class="admin-notice-label">未対応のお問い合わせ</p>
                    <p class="admin-notice-value"><?= (int) $pendingInquiries ?>件</p>
                </div>
            </div>
        </div>

        <?php if (!empty($pendingItems)): ?>
            <div class="admin-notice-list">
                <table>
                    <thead>
                        <tr>
                            <th>種類</th>
                            <th>件名・内容</th>
                            <th>受付日時</th>
                            <th>対応状況</th>
                            <th>詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingItems as $item): ?>
                            <?php
                            $isReport = $item['item_type'] === 'report';
                            $badgeClass = $isReport ? 'admin-notice-badge-report' : 'admin-notice-badge-inquiry';
                            $label = htmlspecialchars($item['item_label'], ENT_QUOTES, 'UTF-8');
                            $title = $item['item_title'] !== null && $item['item_title'] !== ''
                                ? htmlspecialchars($item['item_title'], ENT_QUOTES, 'UTF-8')
                                : '(内容なし)';
                            if ($isReport) {
                                // 違反報告のステータスは日本語ラベルに変換（既存関数を再利用）
                                $statusLabel = getReportStatusLabel($item['item_status']);
                                $status = htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');
                            } else {
                                // お問い合わせのステータスは既に日本語のためそのまま表示
                                $status = htmlspecialchars($item['item_status'], ENT_QUOTES, 'UTF-8');
                            }
                            $createdAt = htmlspecialchars(date('Y/m/d H:i', strtotime($item['item_created_at'])), ENT_QUOTES, 'UTF-8');
                            if ($isReport) {
                                $link = '/Meetory/meetory/reports/admin/detail.php?report_id=' . (int) $item['item_id'];
                            } else {
                                $link = '/Meetory/meetory/reports/admin/inquiry-detail.php?inquiry_id=' . (int) $item['item_id'];
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="admin-notice-badge <?= $badgeClass ?>"><?= $label ?></span>
                                </td>
                                <td><?= $title ?></td>
                                <td><?= $createdAt ?></td>
                                <td><?= $status ?></td>
                                <td>
                                    <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="admin-notice-link">詳細</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="admin-notice-empty">現在、対応が必要な案件はありません。</p>
        <?php endif; ?>
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