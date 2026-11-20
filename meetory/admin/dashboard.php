<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

$pdo = getPDO();

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

// 最近の活動を取得
$activities = [];

// 1. ユーザー登録
try {
    $stmt = $pdo->prepare("
        SELECT u.created_at AS activity_time,
               'registration' AS activity_type,
               'ユーザー登録' AS activity_label,
               CONCAT('ユーザー「', u.user_name, '」が登録されました') AS activity_content,
               u.user_name AS activity_user
        FROM users AS u
        ORDER BY u.created_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }
} catch (Throwable $e) {
    error_log('admin dashboard activity error (user registration): ' . $e->getMessage());
}

// 2. 商品出品
try {
    $stmt = $pdo->prepare("
        SELECT g.listed_at AS activity_time,
               'listing' AS activity_type,
               '商品出品' AS activity_label,
               CONCAT('商品「', g.goods_name, '」が出品されました') AS activity_content,
               u.user_name AS activity_user
        FROM goods AS g
        JOIN users AS u ON g.seller_id = u.user_id
        ORDER BY g.listed_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }
} catch (Throwable $e) {
    error_log('admin dashboard activity error (goods listing): ' . $e->getMessage());
}

// 3. 購入・取引開始
try {
    $stmt = $pdo->prepare("
        SELECT b.purchased_at AS activity_time,
               'purchase' AS activity_type,
               '購入' AS activity_label,
               CONCAT('商品「', SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT g.goods_name ORDER BY g.goods_name SEPARATOR '、'), ',', 50), '」を含む取引を購入しました') AS activity_content,
               bu.user_name AS activity_user
        FROM buy AS b
        JOIN buy_items AS bi ON bi.transaction_id = b.transaction_id
        JOIN goods AS g ON g.goods_id = bi.goods_id
        JOIN users AS bu ON b.buyer_id = bu.user_id
        GROUP BY b.transaction_id, b.purchased_at, bu.user_name
        ORDER BY b.purchased_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }
} catch (Throwable $e) {
    error_log('admin dashboard activity error (purchase): ' . $e->getMessage());
}

// 4. 取引発送
try {
    $stmt = $pdo->prepare("
        SELECT d.shipped_at AS activity_time,
               'shipping' AS activity_type,
               '発送' AS activity_label,
               CONCAT('取引ID ', d.transaction_id, ' を発送しました') AS activity_content,
               GROUP_CONCAT(DISTINCT u.user_name ORDER BY u.user_name SEPARATOR '、') AS activity_user
        FROM deliveries AS d
        JOIN users AS u ON d.seller_id = u.user_id
        GROUP BY d.transaction_id, d.shipped_at
        ORDER BY d.shipped_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }
} catch (Throwable $e) {
    error_log('admin dashboard activity error (shipping): ' . $e->getMessage());
}

// 5. 取引完了
try {
    $stmt = $pdo->prepare("
        SELECT b.completed_at AS activity_time,
               'completion' AS activity_type,
               '取引完了' AS activity_label,
               CONCAT('取引ID ', b.transaction_id, ' の取引が完了しました') AS activity_content,
               bu.user_name AS activity_user
        FROM buy AS b
        JOIN users AS bu ON b.buyer_id = bu.user_id
        WHERE b.transaction_status = '取引完了'
        ORDER BY b.completed_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }
} catch (Throwable $e) {
    error_log('admin dashboard activity error (completion): ' . $e->getMessage());
}

// 6. アカウント制限の作成・解除
try {
    $stmt = $pdo->prepare("
        SELECT al.logged_at AS activity_time,
               CASE al.action
                   WHEN 'created' THEN 'restriction_created'
                   ELSE 'restriction_revoked'
               END AS activity_type,
               CASE al.action
                   WHEN 'created' THEN '制限設定'
                   ELSE '制限解除'
               END AS activity_label,
               CONCAT('ユーザー「', u.user_name, '」へ',
                      CASE al.action
                          WHEN 'created' THEN '制限を設定'
                          ELSE '制限を解除'
                      END,
                      '（', al.restriction_type, '）') AS activity_content,
               u.user_name AS activity_user
        FROM account_restriction_logs AS al
        JOIN users AS u ON al.user_id = u.user_id
        ORDER BY al.logged_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }
} catch (Throwable $e) {
    error_log('admin dashboard activity error (restriction): ' . $e->getMessage());
}

// 日時を基準に新しい順に並べ替え（NULLの場合は0として扱う）
usort($activities, function ($a, $b) {
    $timeA = $a['activity_time'] ? strtotime($a['activity_time']) : 0;
    $timeB = $b['activity_time'] ? strtotime($b['activity_time']) : 0;
    return $timeB <=> $timeA;
});

// 最新15件だけを取得
$activities = array_slice($activities, 0, 15);

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

<main class="main-container admin-container">

    <h1 class="admin-page-title">ダッシュボード詳細</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>ダッシュボード詳細</span>
    </nav>

    <!-- ユーザー登録推移（折れ線グラフ） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">ユーザー登録推移</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="userRegistrationChart"></canvas>
        </div>
    </section>

    <!-- 商品出品推移（棒グラフ） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">商品出品推移</h2>
        <div class="admin-chart-container" style="position: relative; height: 300px;">
            <canvas id="goodsListingChart"></canvas>
        </div>
    </section>

<!-- 最近の活動 -->
<section class="admin-widget">
    <h2 class="admin-widget-title">最近の活動</h2>
    <?php if (empty($activities)): ?>
        <p>最近の活動はありません。</p>
    <?php else: ?>
        <table class="admin-activity-table">
            <thead>
                <tr>
                    <th>日時</th>
                    <th>種類</th>
                    <th>内容</th>
                    <th>ユーザー</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?= htmlspecialchars(date('Y/m/d H:i', strtotime($activity['activity_time'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="admin-activity-badge admin-activity-<?= htmlspecialchars($activity['activity_type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($activity['activity_label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($activity['activity_content'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($activity['activity_user'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

    <!-- セキュリティ状況（プレースホルダー） -->
    <section class="admin-widget">
        <h2 class="admin-widget-title">セキュリティ状況</h2>
        <div class="admin-widget-placeholder">
            <p>ログイン失敗履歴・不審アクセス記録は次の段階で実装します。</p>
        </div>
    </section>

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
