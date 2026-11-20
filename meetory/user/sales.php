<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

require_once '../../meetory-functions.php';

$user_id = (int) $_SESSION['user_id'];
$periods = [
    'all' => '全期間',
    'this_month' => '今月',
    'last_month' => '先月',
    '7days' => '直近7日間',
    '30days' => '直近30日間'
];
$requested_period = $_GET['period'] ?? 'all';
$period = is_string($requested_period) && isset($periods[$requested_period])
    ? $requested_period
    : 'all';

$sales_timezone = new DateTimeZone('Asia/Tokyo');
$now = new DateTimeImmutable('now', $sales_timezone);
$this_month_start = $now->modify('first day of this month')->setTime(0, 0, 0);
$next_month_start = $this_month_start->modify('+1 month');
$last_month_start = $this_month_start->modify('-1 month');
$period_start = null;
$period_end = null;

switch ($period) {
    case 'this_month':
        $period_start = $this_month_start;
        $period_end = $next_month_start;
        break;
    case 'last_month':
        $period_start = $last_month_start;
        $period_end = $this_month_start;
        break;
    case '7days':
        $period_start = $now->modify('-7 days');
        $period_end = $now;
        break;
    case '30days':
        $period_start = $now->modify('-30 days');
        $period_end = $now;
        break;
}

$period_condition = '';
$period_parameters = [];
if ($period_start !== null && $period_end !== null) {
    $period_condition = '
        AND b.purchased_at >= :period_start
        AND b.purchased_at < :period_end';
    $period_parameters = [
        'period_start' => $period_start->format('Y-m-d H:i:s'),
        'period_end' => $period_end->format('Y-m-d H:i:s')
    ];
}

$summary = null;
$selected_summary = null;
$sales_history = [];
$daily_sales = [];
$monthly_sales = [];
$load_error = false;

try {
    $pdo = getPDO();

    $eligible_sales = "
        FROM buy_items AS bi
        INNER JOIN buy AS b
            ON b.transaction_id = bi.transaction_id
        INNER JOIN goods AS g
            ON g.goods_id = bi.goods_id
        WHERE g.seller_id = :seller_id
            AND b.transaction_status IN ('購入済み', '発送済み', '取引完了')
            AND b.cancelled_at IS NULL";

    $summary_stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(bi.purchase_price), 0) AS total_sales,
            COALESCE(SUM(
                CASE
                    WHEN b.purchased_at >= :this_month_start
                        AND b.purchased_at < :next_month_start
                    THEN bi.purchase_price ELSE 0
                END
            ), 0) AS this_month_sales,
            COALESCE(SUM(
                CASE
                    WHEN b.purchased_at >= :this_month_start_count
                        AND b.purchased_at < :next_month_start_count
                    THEN 1 ELSE 0
                END
            ), 0) AS this_month_count,
            COALESCE(SUM(
                CASE
                    WHEN b.purchased_at >= :last_month_start
                        AND b.purchased_at < :this_month_start_last
                    THEN bi.purchase_price ELSE 0
                END
            ), 0) AS last_month_sales,
            COALESCE(SUM(
                CASE
                    WHEN b.purchased_at >= :last_month_start_count
                        AND b.purchased_at < :this_month_start_last_count
                    THEN 1 ELSE 0
                END
            ), 0) AS last_month_count
        ' . $eligible_sales
    );
    $summary_stmt->execute([
        'seller_id' => $user_id,
        'this_month_start' => $this_month_start->format('Y-m-d H:i:s'),
        'next_month_start' => $next_month_start->format('Y-m-d H:i:s'),
        'this_month_start_count' => $this_month_start->format('Y-m-d H:i:s'),
        'next_month_start_count' => $next_month_start->format('Y-m-d H:i:s'),
        'last_month_start' => $last_month_start->format('Y-m-d H:i:s'),
        'this_month_start_last' => $this_month_start->format('Y-m-d H:i:s'),
        'last_month_start_count' => $last_month_start->format('Y-m-d H:i:s'),
        'this_month_start_last_count' => $this_month_start->format('Y-m-d H:i:s')
    ]);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

    $selected_stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS sale_count,
            COALESCE(SUM(bi.purchase_price), 0) AS sales_amount
        ' . $eligible_sales . $period_condition
    );
    $selected_stmt->execute(array_merge(
        ['seller_id' => $user_id],
        $period_parameters
    ));
    $selected_summary = $selected_stmt->fetch(PDO::FETCH_ASSOC);

    $history_stmt = $pdo->prepare(
        'SELECT
            bi.purchase_price,
            b.transaction_id,
            b.purchased_at,
            b.transaction_status,
            g.goods_name
        ' . $eligible_sales . $period_condition . '
        ORDER BY b.purchased_at DESC, b.transaction_id DESC, bi.buy_item_id DESC'
    );
    $history_stmt->execute(array_merge(
        ['seller_id' => $user_id],
        $period_parameters
    ));
    $sales_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

    $daily_stmt = $pdo->prepare(
        'SELECT
            DATE(b.purchased_at) AS sales_date,
            COUNT(*) AS sale_count,
            SUM(bi.purchase_price) AS sales_amount
        ' . $eligible_sales . $period_condition . '
        GROUP BY DATE(b.purchased_at)
        ORDER BY sales_date DESC'
    );
    $daily_stmt->execute(array_merge(
        ['seller_id' => $user_id],
        $period_parameters
    ));
    $daily_sales = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

    $monthly_stmt = $pdo->prepare(
        "SELECT
            DATE_FORMAT(b.purchased_at, '%Y-%m') AS sales_month,
            COUNT(*) AS sale_count,
            SUM(bi.purchase_price) AS sales_amount
        " . $eligible_sales . $period_condition . "
        GROUP BY DATE_FORMAT(b.purchased_at, '%Y-%m')
        ORDER BY sales_month DESC"
    );
    $monthly_stmt->execute(array_merge(
        ['seller_id' => $user_id],
        $period_parameters
    ));
    $monthly_sales = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $load_error = true;
}

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$format_sale_datetime = static function ($value) use ($sales_timezone): string {
    return (new DateTimeImmutable($value, $sales_timezone))->format('Y年m月d日 H:i');
};

require '../../meetory-header.php';
?>

<link
    rel="stylesheet"
    href="/Meetory/meetory/assets/css/sales.css?v=<?= filemtime(__DIR__ . '/../assets/css/sales.css') ?>"
>

<div class="sales-page">
    <header class="sales-heading">
        <h1>売上・販売実績</h1>
        <p>ご自身の商品販売実績と売上状況を確認できます。</p>
    </header>

    <?php if ($load_error): ?>
        <p class="sales-error" role="alert">
            売上データを読み込めませんでした。時間をおいて再度お試しください。
        </p>
    <?php else: ?>
        <section class="sales-summary-grid" aria-label="売上集計">
            <article class="sales-summary-card sales-total-card">
                <h2>累計売上</h2>
                <p class="sales-amount"><?= number_format((int) $summary['total_sales']) ?><span>円</span></p>
            </article>
            <article class="sales-summary-card">
                <h2>販売済み商品数</h2>
                <p class="sales-count"><?= number_format((int) $summary['total_count']) ?><span>件</span></p>
            </article>
            <article class="sales-summary-card">
                <h2>今月の売上</h2>
                <p class="sales-amount"><?= number_format((int) $summary['this_month_sales']) ?><span>円</span></p>
            </article>
            <article class="sales-summary-card">
                <h2>今月の販売数</h2>
                <p class="sales-count"><?= number_format((int) $summary['this_month_count']) ?><span>件</span></p>
            </article>
            <article class="sales-summary-card">
                <h2>前月の売上</h2>
                <p class="sales-amount"><?= number_format((int) $summary['last_month_sales']) ?><span>円</span></p>
            </article>
            <article class="sales-summary-card">
                <h2>前月の販売数</h2>
                <p class="sales-count"><?= number_format((int) $summary['last_month_count']) ?><span>件</span></p>
            </article>
        </section>

        <section class="sales-section">
            <h2>表示期間</h2>
            <nav class="sales-period-nav" aria-label="売上の表示期間">
                <?php foreach ($periods as $period_key => $period_label): ?>
                    <a
                        href="?period=<?= $escape($period_key) ?>"
                        <?= $period === $period_key ? 'aria-current="page"' : '' ?>
                    ><?= $escape($period_label) ?></a>
                <?php endforeach; ?>
            </nav>
            <p class="sales-period-total">
                <?= $escape($periods[$period]) ?>の売上：
                <strong><?= number_format((int) $selected_summary['sales_amount']) ?>円</strong>
                （<?= number_format((int) $selected_summary['sale_count']) ?>件）
            </p>
        </section>

        <section class="sales-section">
            <h2>売上推移（日別）</h2>
            <?php if (empty($daily_sales)): ?>
                <p class="sales-empty">この期間の売上データはありません。</p>
            <?php else: ?>
                <div class="sales-table-wrap">
                    <table class="sales-table">
                        <thead>
                            <tr><th>販売日</th><th>売上</th><th>販売数</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_sales as $row): ?>
                                <tr>
                                    <td><?= $escape(date('Y年m月d日', strtotime($row['sales_date']))) ?></td>
                                    <td><?= number_format((int) $row['sales_amount']) ?>円</td>
                                    <td><?= number_format((int) $row['sale_count']) ?>件</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="sales-section">
            <h2>売上推移（月別）</h2>
            <?php if (empty($monthly_sales)): ?>
                <p class="sales-empty">この期間の売上データはありません。</p>
            <?php else: ?>
                <div class="sales-table-wrap">
                    <table class="sales-table">
                        <thead>
                            <tr><th>販売月</th><th>売上</th><th>販売数</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthly_sales as $row): ?>
                                <tr>
                                    <td><?= $escape(date('Y年m月', strtotime($row['sales_month'] . '-01'))) ?></td>
                                    <td><?= number_format((int) $row['sales_amount']) ?>円</td>
                                    <td><?= number_format((int) $row['sale_count']) ?>件</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="sales-section">
            <h2>販売履歴（<?= $escape($periods[$period]) ?>）</h2>
            <?php if (empty($sales_history)): ?>
                <p class="sales-empty">この期間の販売履歴はありません。</p>
            <?php else: ?>
                <div class="sales-table-wrap">
                    <table class="sales-table sales-history-table">
                        <thead>
                            <tr>
                                <th>商品名</th>
                                <th>販売価格</th>
                                <th>販売日時</th>
                                <th>取引状態</th>
                                <th>取引情報</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_history as $sale): ?>
                                <tr>
                                    <td><?= $escape($sale['goods_name']) ?></td>
                                    <td><?= number_format((int) $sale['purchase_price']) ?>円</td>
                                    <td><?= $escape($format_sale_datetime($sale['purchased_at'])) ?></td>
                                    <td><?= $escape($sale['transaction_status']) ?></td>
                                    <td>
                                        <a href="../purchase/transaction-detail.php?transaction_id=<?= (int) $sale['transaction_id'] ?>">
                                            取引詳細
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <p class="sales-back-link"><a href="mypage.php">マイページに戻る</a></p>
</div>

<?php require '../../meetory-footer.php'; ?>
