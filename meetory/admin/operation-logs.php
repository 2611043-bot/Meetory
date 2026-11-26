<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

$pdo = getPDO();

// 検索・絞り込みパラメータ
$keyword = $_GET['keyword'] ?? '';
$action_type_filter = $_GET['action_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$keyword = is_string($keyword) ? trim($keyword) : '';
$action_type_filter = is_string($action_type_filter) ? $action_type_filter : '';
$date_from = is_string($date_from) ? trim($date_from) : '';
$date_to = is_string($date_to) ? trim($date_to) : '';

// ページ番号
$page_value = $_GET['page'] ?? '1';
$page = is_string($page_value) ? filter_var($page_value, FILTER_VALIDATE_INT) : false;
$page = $page !== false && $page !== null && $page > 0 ? $page : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 基本SQL
$base_sql = '
FROM admin_operation_logs AS l
LEFT JOIN users AS u
    ON u.user_id = l.admin_id
WHERE 1 = 1';

$conditions = [];
$params = [];

// キーワード検索（管理者名・操作内容・対象種別・対象ID・操作詳細）
if ($keyword !== '') {
    $like = '%' . $keyword . '%';
    $search_conditions = [
        'u.user_name LIKE :keyword_user',
        'l.action_type LIKE :keyword_action',
        'l.target_type LIKE :keyword_target',
        'l.details LIKE :keyword_details',
    ];
    $params[':keyword_user'] = $like;
    $params[':keyword_action'] = $like;
    $params[':keyword_target'] = $like;
    $params[':keyword_details'] = $like;
    if (ctype_digit($keyword)) {
        $search_conditions[] = 'l.target_id = :keyword_id';
        $params[':keyword_id'] = (int) $keyword;
    }
    $conditions[] = '(' . implode(' OR ', $search_conditions) . ')';
}

// 操作種別による絞り込み
if ($action_type_filter !== '') {
    $conditions[] = 'l.action_type = :action_type';
    $params[':action_type'] = $action_type_filter;
}

// 開始日による絞り込み
if ($date_from !== '') {
    $date_from_parsed = date('Y-m-d H:i:s', strtotime($date_from . ' 00:00:00'));
    if ($date_from_parsed !== false) {
        $conditions[] = 'l.created_at >= :date_from';
        $params[':date_from'] = $date_from_parsed;
    }
}

// 終了日による絞り込み
if ($date_to !== '') {
    $date_to_parsed = date('Y-m-d H:i:s', strtotime($date_to . ' 23:59:59'));
    if ($date_to_parsed !== false) {
        $conditions[] = 'l.created_at <= :date_to';
        $params[':date_to'] = $date_to_parsed;
    }
}

$where_sql = '';
if (count($conditions) > 0) {
    $where_sql = ' AND ' . implode(' AND ', $conditions);
}

// 総件数取得
$count_sql = 'SELECT COUNT(*) ' . $base_sql . $where_sql;
$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $name => $value) {
    $count_stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$count_stmt->execute();
$total_count = (int) $count_stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_count / $per_page));
$page = min($page, $total_pages);

// 操作ログ一覧取得
$data_sql = '
    SELECT
        l.log_id,
        l.admin_id,
        u.user_name AS admin_name,
        l.action_type,
        l.target_type,
        l.target_id,
        l.details,
        l.ip_address,
        l.created_at
    ' . $base_sql . $where_sql . '
    ORDER BY l.created_at DESC, l.log_id DESC
    LIMIT :limit OFFSET :offset';

$data_stmt = $pdo->prepare($data_sql);
foreach ($params as $name => $value) {
    $data_stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$data_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$data_stmt->execute();
$logs = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

// Ajax レスポンス：検索結果フラグメントのみ出力（フォーカス維持のためページ全体再読み込みを回避）
if ($_GET['ajax'] ?? '' === '1') {
    header('Content-Type: text/html; charset=utf-8');
    $escape_ajax = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $action_type_labels_ajax = [
        'goods_delete' => '商品削除',
        'goods_publish_stop' => '商品公開停止',
        'goods_publish_resume' => '商品公開再開',
        'inquiry_reply' => 'お問い合わせ返信',
        'inquiry_status_change' => 'お問い合わせステータス変更',
        'restriction_create' => 'アカウント利用制限',
        'restriction_revoke' => 'アカウント利用制限解除',
        'report_status_change' => '違反報告ステータス変更',
    ];
    ?>
    <?php if (!empty($logs)): ?>
        <p class="admin-operation-count"><?= number_format($total_count) ?>件</p>
        <table class="admin-operation-logs-table">
            <thead>
                <tr>
                    <th>操作日時</th>
                    <th>操作した管理者</th>
                    <th>操作種別</th>
                    <th>対象種別</th>
                    <th>対象ID</th>
                    <th>操作の詳細</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $adminName = $log['admin_name'] !== null && $log['admin_name'] !== ''
                        ? $escape_ajax($log['admin_name'])
                        : '削除済みユーザー';
                    $actionTypeLabel = $action_type_labels_ajax[$log['action_type']] ?? $log['action_type'];
                    $targetType = $log['target_type'] !== null && $log['target_type'] !== ''
                        ? $escape_ajax($log['target_type'])
                        : '－';
                    $targetId = $log['target_id'] !== null
                        ? (int) $log['target_id']
                        : '－';
                    $details = $log['details'] !== null && $log['details'] !== ''
                        ? $escape_ajax($log['details'])
                        : '－';
                    $createdAt = $escape_ajax(date('Y/m/d H:i', strtotime($log['created_at'])));
                    ?>
                    <tr>
                        <td><?= $createdAt ?></td>
                        <td><?= $adminName ?></td>
                        <td>
                            <span class="admin-operation-badge"><?= $escape_ajax($actionTypeLabel) ?></span>
                        </td>
                        <td><?= $targetType ?></td>
                        <td><?= $targetId ?></td>
                        <td><?= $details ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <?php
            $page_link_ajax = function ($target_page) use ($base_query): string {
                $sep = $base_query === '' ? '?' : '&';
                return $base_query . $sep . 'page=' . (int) $target_page;
            };
            ?>
            <nav class="admin-operation-pagination" aria-label="ページ送り">
                <?php if ($page > 1): ?>
                    <a href="<?= $page_link_ajax($page - 1) ?>">前へ</a>
                <?php endif; ?>
                <span><?= (int) $page ?> / <?= (int) $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="<?= $page_link_ajax($page + 1) ?>">次へ</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="admin-operation-empty">操作ログはありません。</div>
    <?php endif; ?>
    <?php
    exit;
}

// 操作種別一覧（フィルタ用、DBに存在するものだけ）
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

$available_action_types = [];
if (!empty($logs)) {
    $distinct_types = array_unique(array_column($logs, 'action_type'));
    foreach ($distinct_types as $type) {
        $available_action_types[$type] = $action_type_labels[$type] ?? $type;
    }
}

// CSRFトークンを作成（共通パターンに合わせる）
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$filter_params = [];
if ($keyword !== '') {
    $filter_params['keyword'] = $keyword;
}
if ($action_type_filter !== '') {
    $filter_params['action_type'] = $action_type_filter;
}
if ($date_from !== '') {
    $filter_params['date_from'] = $date_from;
}
if ($date_to !== '') {
    $filter_params['date_to'] = $date_to;
}
$filter_query = http_build_query(array_filter($filter_params));
$base_query = $filter_query !== '' ? '?' . $filter_query : '';

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
/* 操作ログ一覧テーブル */
.admin-operation-logs-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.admin-operation-logs-table th {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-align: left;
    white-space: nowrap;
}

.admin-operation-logs-table td {
    border-bottom: 1px solid #f1f5f9;
    padding: 12px 16px;
    font-size: 13px;
    color: #334155;
}

.admin-operation-logs-table tr:last-child td {
    border-bottom: none;
}

.admin-operation-logs-table tr:hover {
    background: #f8fafc;
}

/* 操作種別バッジ */
.admin-operation-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* 検索・絞り込みフォーム */
.admin-operation-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 14px;
    margin: 0 0 20px;
    padding: 16px 18px;
    border: 1px solid #e2ebf5;
    border-radius: 14px;
    background-color: #f8fbff;
}

.admin-operation-filters label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}

.admin-operation-filters input,
.admin-operation-filters select {
    height: 38px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background-color: #ffffff;
    font-size: 13px;
    color: #0f172a;
    box-sizing: border-box;
}

.admin-operation-filters select {
    min-width: 140px;
    cursor: pointer;
}

.admin-operation-filters input[type="search"] {
    min-width: 210px;
}

.admin-operation-filters input[type="date"] {
    min-width: 150px;
}

.admin-operation-filters button[type="submit"] {
    height: 38px;
    padding: 0 20px;
    border: none;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.admin-operation-filters button[type="submit"]:hover {
    background-color: #1565c0;
}

.admin-operation-filters a {
    display: inline-flex;
    align-items: center;
    height: 38px;
    padding: 0 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #ffffff;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    box-sizing: border-box;
}

.admin-operation-filters a:hover {
    background-color: #f1f5f9;
    color: #0f2942;
}

/* 件数表示 */
.admin-operation-count {
    margin: 0 0 12px;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

/* ページネーション */
.admin-operation-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 22px;
}

.admin-operation-pagination a {
    display: inline-flex;
    align-items: center;
    padding: 7px 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #f8fbff;
    color: #1976d2;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s;
}

.admin-operation-pagination a:hover {
    background-color: #e8f3fe;
    border-color: #90caf9;
}

.admin-operation-pagination span {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

/* 空状態 */
.admin-operation-empty {
    padding: 40px 20px;
    text-align: center;
    color: #64748b;
    font-size: 15px;
}

/* 詳細リンク */
.admin-operation-link {
    color: #1976d2;
    font-weight: 700;
    text-decoration: none;
    font-size: 12px;
}

.admin-operation-link:hover {
    text-decoration: underline;
}

/* レスポンシブ */
@media (max-width: 768px) {
    .admin-main-content {
        padding: 20px 16px;
    }
    .admin-operation-table,
    .admin-operation-logs-table {
        min-width: 700px;
    }
    .admin-operation-filters {
        flex-direction: column;
        align-items: stretch;
    }
    .admin-operation-filters input[type="search"],
    .admin-operation-filters input[type="date"],
    .admin-operation-filters select {
        min-width: auto;
        width: 100%;
    }
    .admin-operation-filters button[type="submit"],
    .admin-operation-filters a {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-container admin-container">
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1 class="admin-page-title">操作ログ</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>操作ログ</span>
    </nav>

    <form id="operation-filter-form" class="admin-operation-filters" method="get" action="operation-logs.php">
        <label>
            キーワード（管理者名・操作内容など）
            <input type="search" name="keyword" maxlength="100" value="<?= $escape($keyword) ?>">
        </label>
        <label>
            操作種別
            <select name="action_type">
                <option value="">すべて</option>
                <?php foreach ($available_action_types as $type => $label): ?>
                    <option value="<?= $escape($type) ?>" <?= $action_type_filter === $type ? 'selected' : '' ?>>
                        <?= $escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            開始日
            <input type="date" name="date_from" value="<?= $escape($date_from) ?>">
        </label>
        <label>
            終了日
            <input type="date" name="date_to" value="<?= $escape($date_to) ?>">
        </label>
        <a href="operation-logs.php">条件をクリア</a>
    </form>

    <div id="operation-results">
    <?php if (!empty($logs)): ?>
        <p class="admin-operation-count"><?= number_format($total_count) ?>件</p>
        <table class="admin-operation-logs-table">
            <thead>
                <tr>
                    <th>操作日時</th>
                    <th>操作した管理者</th>
                    <th>操作種別</th>
                    <th>対象種別</th>
                    <th>対象ID</th>
                    <th>操作の詳細</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $adminName = $log['admin_name'] !== null && $log['admin_name'] !== ''
                        ? htmlspecialchars($log['admin_name'], ENT_QUOTES, 'UTF-8')
                        : '削除済みユーザー';
                    $actionTypeLabel = $action_type_labels[$log['action_type']] ?? $log['action_type'];
                    $targetType = $log['target_type'] !== null && $log['target_type'] !== ''
                        ? htmlspecialchars($log['target_type'], ENT_QUOTES, 'UTF-8')
                        : '－';
                    $targetId = $log['target_id'] !== null
                        ? (int) $log['target_id']
                        : '－';
                    $details = $log['details'] !== null && $log['details'] !== ''
                        ? htmlspecialchars($log['details'], ENT_QUOTES, 'UTF-8')
                        : '－';
                    $createdAt = htmlspecialchars(date('Y/m/d H:i', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8');
                    $logId = (int) $log['log_id'];
                    $detailLink = 'operation-log-detail.php?log_id=' . $logId;
                    ?>
                    <tr>
                        <td><?= $createdAt ?></td>
                        <td><?= $adminName ?></td>
                        <td>
                            <span class="admin-operation-badge"><?= htmlspecialchars($actionTypeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td><?= $targetType ?></td>
                        <td><?= $targetId ?></td>
                        <td><?= $details ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($detailLink, ENT_QUOTES, 'UTF-8') ?>" class="admin-operation-link">詳細</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <?php
            $page_link = function ($target_page) use ($base_query): string {
                $sep = $base_query === '' ? '?' : '&';
                return $base_query . $sep . 'page=' . (int) $target_page;
            };
            ?>
            <nav class="admin-operation-pagination" aria-label="ページ送り">
                <?php if ($page > 1): ?>
                    <a href="<?= $page_link($page - 1) ?>">前へ</a>
                <?php endif; ?>
                <span><?= (int) $page ?> / <?= (int) $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="<?= $page_link($page + 1) ?>">次へ</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        </div>
        <div class="admin-operation-empty">操作ログはありません。</div>
    <?php endif; ?>

        </div>
    </div>
</main>

<?php require '../../meetory-footer.php'; ?>

<script>
(function () {
    'use strict';

    var form = document.getElementById('operation-filter-form');
    var keywordInput = form.querySelector('input[name="keyword"]');
    var actionTypeSelect = form.querySelector('select[name="action_type"]');
    var dateFromInput = form.querySelector('input[name="date_from"]');
    var dateToInput = form.querySelector('input[name="date_to"]');
    var resultsContainer = document.getElementById('operation-results');
    var timer = null;
    var controller = null;

    if (!form || !resultsContainer) {
        return;
    }

    function buildQueryString() {
        var params = new URLSearchParams();
        if (keywordInput.value !== '') {
            params.append('keyword', keywordInput.value);
        }
        if (actionTypeSelect.value !== '') {
            params.append('action_type', actionTypeSelect.value);
        }
        if (dateFromInput.value !== '') {
            params.append('date_from', dateFromInput.value);
        }
        if (dateToInput.value !== '') {
            params.append('date_to', dateToInput.value);
        }
        params.append('ajax', '1');
        return params.toString();
    }

    function fetchResults() {
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();

        var url = form.action + '?' + buildQueryString();

        fetch(url, {
            method: 'GET',
            signal: controller.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('ネットワークエラーが発生しました。');
            }
            return response.text();
        })
        .then(function(html) {
            resultsContainer.innerHTML = html;
        })
        .catch(function(err) {
            if (err.name !== 'AbortError') {
                console.error(err);
                resultsContainer.innerHTML = '<div class="admin-operation-empty">検索結果の取得に失敗しました。</div>';
            }
        });
    }

    function debounceFetch() {
        if (timer !== null) {
            clearTimeout(timer);
        }
        timer = setTimeout(function() {
            timer = null;
            fetchResults();
        }, 400);
    }

    form.addEventListener('input', function(event) {
        if (event.target.name === 'keyword') {
            debounceFetch();
        }
    });

    form.addEventListener('change', function(event) {
        if (event.target.tagName === 'SELECT' || event.target.type === 'date') {
            debounceFetch();
        }
    });

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        if (controller) {
            controller.abort();
            controller = null;
        }
        fetchResults();
    });
})();
</script>
