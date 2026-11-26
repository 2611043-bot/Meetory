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

// 期間フィルタ
$period = $_GET['period'] ?? 'all';
$period = is_string($period) ? $period : 'all';
$valid_periods = ['all', 'month', 'last_month', '7days', '30days'];
if (!in_array($period, $valid_periods, true)) {
    $period = 'all';
}

// 期間の開始日・終了日を算出
$date_from = null;
$date_to = null;

switch ($period) {
    case 'month':
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t 23:59:59');
        break;
    case 'last_month':
        $date_from = date('Y-m-01', strtotime('first day of last month'));
        $date_to = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        break;
    case '7days':
        $date_from = date('Y-m-d', strtotime('-6 days'));
        $date_to = date('Y-m-d 23:59:59');
        break;
    case '30days':
        $date_from = date('Y-m-d', strtotime('-29 days'));
        $date_to = date('Y-m-d 23:59:59');
        break;
    case 'all':
    default:
        $date_from = null;
        $date_to = null;
        break;
}

// 集計クエリのWHERE句
$conditions = [];
$params = [];

if ($date_from !== null) {
    $conditions[] = 'l.created_at >= :date_from';
    $params[':date_from'] = $date_from;
}
if ($date_to !== null) {
    $conditions[] = 'l.created_at <= :date_to';
    $params[':date_to'] = $date_to;
}

$where_sql = '';
if (count($conditions) > 0) {
    $where_sql = ' WHERE ' . implode(' AND ', $conditions);
}

// 総件数
$total_stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_operation_logs AS l' . $where_sql);
foreach ($params as $name => $value) {
    $total_stmt->bindValue($name, $value, PDO::PARAM_STR);
}
$total_stmt->execute();
$total_count = (int) $total_stmt->fetchColumn();

// 操作した管理者の人数
$admin_count_stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT l.admin_id) FROM admin_operation_logs AS l' . $where_sql
);
foreach ($params as $name => $value) {
    $admin_count_stmt->bindValue($name, $value, PDO::PARAM_STR);
}
$admin_count_stmt->execute();
$admin_count = (int) $admin_count_stmt->fetchColumn();

// 操作種別ごとの件数
$action_stmt = $pdo->prepare(
    'SELECT l.action_type, COUNT(*) AS cnt
    FROM admin_operation_logs AS l' . $where_sql . '
    GROUP BY l.action_type
    ORDER BY cnt DESC'
);
foreach ($params as $name => $value) {
    $action_stmt->bindValue($name, $value, PDO::PARAM_STR);
}
$action_stmt->execute();
$action_counts = $action_stmt->fetchAll(PDO::FETCH_ASSOC);

// 日別の操作回数（過去30日間）
$daily_stmt = $pdo->prepare(
    'SELECT DATE(l.created_at) AS day, COUNT(*) AS cnt
    FROM admin_operation_logs AS l' . $where_sql . '
    GROUP BY DATE(l.created_at)
    ORDER BY day ASC'
);
foreach ($params as $name => $value) {
    $daily_stmt->bindValue($name, $value, PDO::PARAM_STR);
}
$daily_stmt->execute();
$daily_counts = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

// 月別の操作回数（過去12か月）
$monthly_stmt = $pdo->prepare(
    'SELECT DATE_FORMAT(l.created_at, "%Y-%m") AS month, COUNT(*) AS cnt
    FROM admin_operation_logs AS l' . $where_sql . '
    GROUP BY DATE_FORMAT(l.created_at, "%Y-%m")
    ORDER BY month ASC'
);
foreach ($params as $name => $value) {
    $monthly_stmt->bindValue($name, $value, PDO::PARAM_STR);
}
$monthly_stmt->execute();
$monthly_counts = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// 管理者ごとの操作回数
$admin_stmt = $pdo->prepare(
    'SELECT u.user_id, u.user_name, COUNT(*) AS cnt
    FROM admin_operation_logs AS l
    LEFT JOIN users AS u ON u.user_id = l.admin_id' . $where_sql . '
    GROUP BY l.admin_id, u.user_name
    ORDER BY cnt DESC'
);
foreach ($params as $name => $value) {
    $admin_stmt->bindValue($name, $value, PDO::PARAM_STR);
}
$admin_stmt->execute();
$admin_counts = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);

// 期間ラベル
$period_labels = [
    'all' => '全期間',
    'month' => '今月',
    'last_month' => '先月',
    '7days' => '過去7日間',
    '30days' => '過去30日間',
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

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" defer></script>
<style>
/* 操作ログ集計専用スタイル */
.admin-operation-summary {
    max-width: 100%;
}

.admin-stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin: 0 0 24px;
}

.admin-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.admin-stat-title {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    margin: 0 0 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.admin-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1a3a5c;
    margin: 0;
}

/* 期間フィルタ */
.admin-period-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 0 24px;
    padding: 16px 18px;
    border: 1px solid #e2ebf5;
    border-radius: 14px;
    background-color: #f8fbff;
}

.admin-period-filter label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    margin: 0;
}

.admin-period-filter input[type="radio"] {
    margin: 0;
}

.admin-period-filter input[type="radio"]:checked + span {
    color: #1a3a5c;
    font-weight: 700;
}

/* グラフセクション */
.admin-chart-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    margin: 0 0 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.admin-chart-section h3 {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 16px;
}

.admin-chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

/* 操作種別リスト */
.admin-action-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 12px;
}

.admin-action-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    text-decoration: none;
    color: #1e293b;
    transition: background 0.15s, border-color 0.15s;
}

.admin-action-item:hover {
    background: #f1f5f9;
    border-color: #90caf9;
}

.admin-action-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    color: #ffffff;
}

.admin-action-count {
    font-size: 18px;
    font-weight: 700;
    color: #1a3a5c;
    margin-left: auto;
}

.admin-action-label {
    font-size: 13px;
    color: #334155;
}

/* 空状態 */
.admin-summary-empty {
    padding: 40px 20px;
    text-align: center;
    color: #64748b;
    font-size: 15px;
}

/* レスポンシブ */
@media (max-width: 768px) {
    .admin-main-content {
        padding: 20px 16px;
    }
    .admin-chart-grid {
        grid-template-columns: 1fr;
    }
    .admin-period-filter {
        flex-direction: column;
        align-items: stretch;
    }
    .admin-period-filter label {
        flex-direction: row;
        justify-content: space-between;
    }
}
</style>

<main class="main-container admin-container">
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1 class="admin-page-title">操作ログ集計</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>操作ログ</span>
        <span class="admin-breadcrumb-separator">›</span>
        <span>集計</span>
    </nav>

    <!-- 統計カード -->
    <div class="admin-stat-cards">
        <div class="admin-stat-card">
            <p class="admin-stat-title">総操作ログ数</p>
            <p class="admin-stat-value"><?= number_format($total_count) ?></p>
        </div>
        <div class="admin-stat-card">
            <p class="admin-stat-title">操作した管理者数</p>
            <p class="admin-stat-value"><?= number_format($admin_count) ?></p>
        </div>
    </div>

    <!-- 期間フィルタ -->
    <form id="period-filter-form" class="admin-period-filter" method="get" action="operation-log-summary.php">
        <label>
            <input type="radio" name="period" value="all"<?= $period === 'all' ? ' checked' : '' ?>>
            <span>全期間</span>
        </label>
        <label>
            <input type="radio" name="period" value="month"<?= $period === 'month' ? ' checked' : '' ?>>
            <span>今月</span>
        </label>
        <label>
            <input type="radio" name="period" value="last_month"<?= $period === 'last_month' ? ' checked' : '' ?>>
            <span>先月</span>
        </label>
        <label>
            <input type="radio" name="period" value="7days"<?= $period === '7days' ? ' checked' : '' ?>>
            <span>過去7日間</span>
        </label>
        <label>
            <input type="radio" name="period" value="30days"<?= $period === '30days' ? ' checked' : '' ?>>
            <span>過去30日間</span>
        </label>
    </form>

    <?php if ($total_count > 0): ?>
        <!-- 操作種別ごとの件数（リスト＋リンク） -->
        <section class="admin-chart-section">
            <h3>操作種別ごとの件数</h3>
            <div class="admin-action-list">
                <?php foreach ($action_counts as $ac): ?>
                    <?php
                    $label = $action_type_labels[$ac['action_type']] ?? $ac['action_type'];
                    $link = 'operation-logs.php?action_type=' . urlencode($ac['action_type']);
                    if ($date_from !== null) {
                        $link .= '&date_from=' . urlencode($date_from);
                    }
                    if ($date_to !== null) {
                        $link .= '&date_to=' . urlencode($date_to);
                    }
                    ?>
                    <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="admin-action-item">
                        <span class="admin-action-badge" style="background: #1a3a5c;"><?= htmlspecialchars(mb_substr($label, 0, 1), ENT_QUOTES, 'UTF-8') ?></span>
                        <div>
                            <p class="admin-action-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="admin-action-count"><?= (int) $ac['cnt'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- グラフセクション -->
        <div class="admin-chart-grid">
            <!-- 操作種別ごとの件数（円グラフ） -->
            <section class="admin-chart-section">
                <h3>操作種別ごとの割合</h3>
                <div class="admin-chart-container">
                    <canvas id="actionTypeChart"></canvas>
                </div>
            </section>

            <!-- 日別の操作回数（折れ線） -->
            <section class="admin-chart-section">
                <h3>日別の操作回数</h3>
                <div class="admin-chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </section>

            <!-- 月別の操作回数（棒グラフ） -->
            <section class="admin-chart-section">
                <h3>月別の操作回数</h3>
                <div class="admin-chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </section>
        </div>

        <!-- 管理者ごとの操作回数 -->
        <section class="admin-chart-section">
            <h3>管理者ごとの操作回数</h3>
            <table class="admin-activity-table">
                <thead>
                    <tr>
                        <th>管理者名</th>
                        <th>操作回数</th>
                        <th>割合</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admin_counts as $ac): ?>
                        <?php
                        $adminName = $ac['user_name'] !== null && $ac['user_name'] !== ''
                            ? htmlspecialchars($ac['user_name'], ENT_QUOTES, 'UTF-8')
                            : '削除済みユーザー';
                        $percent = $total_count > 0 ? round((int) $ac['cnt'] / $total_count * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= $adminName ?></td>
                            <td><?= (int) $ac['cnt'] ?></td>
                            <td><?= $percent ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

    <?php else: ?>
        <div class="admin-summary-empty">指定した期間の操作ログはありません。</div>
    <?php endif; ?>

        </div>
    </div>
</main>

<?php require '../../meetory-footer.php'; ?>

<script>
(function () {
    'use strict';

    // 期間ラジオボタンの変更で自動サブミット
    const form = document.getElementById('period-filter-form');
    if (form) {
        form.addEventListener('change', function () {
            form.submit();
        });
    }

    // 操作種別ごとの割合（円グラフ）
    const actionLabels = <?= json_encode(array_map(fn ($ac) => $action_type_labels[$ac['action_type']] ?? $ac['action_type'], $action_counts), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const actionData = <?= json_encode(array_column($action_counts, 'cnt'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (actionLabels.length > 0) {
        const ctx = document.getElementById('actionTypeChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: actionLabels,
                    datasets: [{
                        data: actionData,
                        backgroundColor: [
                            '#1a3a5c', '#2d5a87', '#4a90d9', '#7bb3e8',
                            '#e0f2fe', '#fef3c7', '#dcfce7', '#ede9fe'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 12 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.chart._metasets[0].total || 0;
                                    const percent = total > 0 ? (value / total * 100).toFixed(1) : 0;
                                    return label + ': ' + value + '件 (' + percent + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // 日別の操作回数（折れ線）
    const dailyLabels = <?= json_encode(array_column($daily_counts, 'day'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const dailyData = <?= json_encode(array_column($daily_counts, 'cnt'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (dailyLabels.length > 0) {
        const ctx = document.getElementById('dailyChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: '操作回数',
                        data: dailyData,
                        borderColor: '#1a3a5c',
                        backgroundColor: 'rgba(26, 58, 92, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#1a3a5c',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: { title: { display: false } }
                    }
                }
            });
        }
    }

    // 月別の操作回数（棒グラフ）
    const monthlyLabels = <?= json_encode(array_column($monthly_counts, 'month'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const monthlyData = <?= json_encode(array_column($monthly_counts, 'cnt'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (monthlyLabels.length > 0) {
        const ctx = document.getElementById('monthlyChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: '操作回数',
                        data: monthlyData,
                        backgroundColor: '#1a3a5c',
                        borderRadius: 4,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: { title: { display: false } }
                    }
                }
            });
        }
    }
})();
</script>
