<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

$pdo = getPDO();

// ページ番号
$page_value = $_GET['page'] ?? '1';
$page = is_string($page_value) ? filter_var($page_value, FILTER_VALIDATE_INT) : false;
$page = $page !== false && $page !== null && $page > 0 ? $page : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 検索・絞り込みパラメータ
$keyword = $_GET['keyword'] ?? '';
$is_active_filter = $_GET['is_active'] ?? '';
$is_sold_filter = $_GET['is_sold'] ?? '';

$keyword = is_string($keyword) ? trim($keyword) : '';
$is_active_filter = is_string($is_active_filter) && in_array($is_active_filter, ['0', '1'], true) ? $is_active_filter : '';
$is_sold_filter = is_string($is_sold_filter) && in_array($is_sold_filter, ['0', '1'], true) ? $is_sold_filter : '';

// 基本SQL
$base_sql = '
FROM goods AS g
INNER JOIN users AS u
    ON u.user_id = g.seller_id
LEFT JOIN auctions AS a
    ON a.goods_id = g.goods_id
WHERE 1 = 1';

$conditions = [];
$params = [];

// キーワード検索（商品名・商品ID）
if ($keyword !== '') {
    $like = '%' . $keyword . '%';
    $search_conditions = [
        'g.goods_name LIKE :keyword_name',
    ];
    $params[':keyword_name'] = $like;
    if (ctype_digit($keyword)) {
        $search_conditions[] = 'g.goods_id = :keyword_id';
        $params[':keyword_id'] = (int) $keyword;
    }
    $conditions[] = '(' . implode(' OR ', $search_conditions) . ')';
}

// 公開状態で絞り込み
if ($is_active_filter !== '') {
    $conditions[] = 'g.is_active = :is_active';
    $params[':is_active'] = (int) $is_active_filter;
}

// 販売状態で絞り込み
if ($is_sold_filter !== '') {
    $conditions[] = 'g.is_sold = :is_sold';
    $params[':is_sold'] = (int) $is_sold_filter;
}

$where_sql = '';
if (count($conditions) > 0) {
    $where_sql = ' AND ' . implode(' AND ', $conditions);
}

// 総件数取得
$count_sql = 'SELECT COUNT(DISTINCT g.goods_id) ' . $base_sql . $where_sql;
$count_stmt = $pdo->prepare($count_sql);
foreach ($params as $name => $value) {
    $count_stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$count_stmt->execute();
$total_count = (int) $count_stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_count / $per_page));
$page = min($page, $total_pages);

// 商品一覧取得
$data_sql = '
    SELECT
        g.goods_id,
        g.seller_id,
        g.goods_name,
        g.price,
        g.listed_at,
        g.is_sold,
        g.is_active,
        u.user_name AS seller_name,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path,
        a.status AS auction_status,
        a.auction_id
    ' . $base_sql . $where_sql . '
    ORDER BY g.listed_at DESC, g.goods_id DESC
    LIMIT :limit OFFSET :offset';

$data_stmt = $pdo->prepare($data_sql);
foreach ($params as $name => $value) {
    $data_stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$data_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$data_stmt->execute();
$goods_list = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

// CSRFトークンを作成
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

$page_suffix = '';
$filter_params = [];
if ($keyword !== '') {
    $filter_params['keyword'] = $keyword;
}
if ($is_active_filter !== '') {
    $filter_params['is_active'] = $is_active_filter;
}
if ($is_sold_filter !== '') {
    $filter_params['is_sold'] = $is_sold_filter;
}
$filter_query = http_build_query(array_filter($filter_params));
$base_query = $filter_query !== '' ? '?' . $filter_query : '';

// Ajax レスポンス：検索結果フラグメントのみ出力（フォーカス維持のためページ全体再読み込みを回避）
if ($_GET['ajax'] ?? '' === '1') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <?php if (!empty($goods_list)): ?>
        <p class="admin-goods-count"><?= number_format($total_count) ?>件</p>
        <table class="admin-goods-table">
            <thead>
                <tr>
                    <th>画像</th>
                    <th>商品ID</th>
                    <th>商品名</th>
                    <th>出品者</th>
                    <th>価格</th>
                    <th>出品日時</th>
                    <th>販売状態</th>
                    <th>公開状態</th>
                    <th>オークション状態</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($goods_list as $goods): ?>
                    <tr>
                        <td>
                            <?php
                            $image_path = $goods['image_path'] ?? '';
                            $is_valid_image = is_string($image_path)
                                && preg_match('~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i', $image_path)
                                && is_file(__DIR__ . '/../../' . $image_path);
                            ?>
                            <?php if ($is_valid_image): ?>
                                <img
                                    src="/Meetory/<?= $escape($image_path) ?>"
                                    alt=""
                                    class="admin-goods-thumb"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div class="admin-goods-thumb" style="background:#e2e8f0;"></div>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $goods['goods_id'] ?></td>
                        <td><?= $escape($goods['goods_name']) ?></td>
                        <td><?= $escape($goods['seller_name']) ?></td>
                        <td><?= number_format((int) $goods['price']) ?>円</td>
                        <td><?= $escape($goods['listed_at']) ?></td>
                        <td>
                            <span class="admin-goods-badge <?= (int) $goods['is_sold'] === 1 ? 'admin-goods-badge-sold' : 'admin-goods-badge-active' ?>">
                                <?= (int) $goods['is_sold'] === 1 ? '売却済み' : '販売中' ?>
                            </span>
                        </td>
                        <td>
                            <span class="admin-goods-badge <?= (int) $goods['is_active'] === 1 ? 'admin-goods-badge-active' : 'admin-goods-badge-inactive' ?>">
                                <?= (int) $goods['is_active'] === 1 ? '公開中' : '公開停止' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($goods['auction_status'])): ?>
                                <span class="admin-goods-badge admin-goods-badge-auction">
                                    <?= $escape($goods['auction_status']) ?>
                                </span>
                            <?php else: ?>
                                <span>-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>">詳細</a>
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
            <nav class="admin-goods-pagination" aria-label="ページ送り">
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
        <p>該当する商品はありません。</p>
    <?php endif; ?>
    <?php
    exit;
}

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
<style>
/* 商品管理一覧テーブル */
.admin-goods-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.admin-goods-table th {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-align: left;
    white-space: nowrap;
}

.admin-goods-table td {
    border-bottom: 1px solid #f1f5f9;
    padding: 12px 16px;
    font-size: 13px;
    color: #334155;
}

.admin-goods-table tr:last-child td {
    border-bottom: none;
}

.admin-goods-table tr:hover {
    background: #f8fafc;
}

/* ステータスバッジ */
.admin-goods-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.admin-goods-badge-active {
    background: #dcfce7;
    color: #15803d;
}

.admin-goods-badge-inactive {
    background: #fecaca;
    color: #991b1b;
}

.admin-goods-badge-sold {
    background: #e2e8f0;
    color: #475569;
}

.admin-goods-badge-auction {
    background: #fef3c7;
    color: #b45309;
}

/* 画像サムネイル */
.admin-goods-thumb {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}

/* 検索・絞り込みフォーム */
.admin-goods-filters {
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

.admin-goods-filters label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}

.admin-goods-filters input,
.admin-goods-filters select {
    height: 38px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background-color: #ffffff;
    font-size: 13px;
    color: #0f172a;
    box-sizing: border-box;
}

.admin-goods-filters select {
    min-width: 140px;
    cursor: pointer;
}

.admin-goods-filters input[type="search"] {
    min-width: 210px;
}

.admin-goods-filters button[type="submit"] {
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

.admin-goods-filters button[type="submit"]:hover {
    background-color: #1565c0;
}

.admin-goods-filters a {
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

.admin-goods-filters a:hover {
    background-color: #f1f5f9;
    color: #0f2942;
}

/* 条件をクリアボタン（リンクと同系の控えめなスタイル） */
.admin-goods-reset-btn {
    height: 38px;
    padding: 0 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #ffffff;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    box-sizing: border-box;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
}

.admin-goods-reset-btn:hover {
    background-color: #f1f5f9;
    color: #0f2942;
    border-color: #bfdbfe;
}

/* 件数表示・アラート */
.admin-goods-count {
    margin: 0 0 12px;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

/* ページネーション */
.admin-goods-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 22px;
}

.admin-goods-pagination a {
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

.admin-goods-pagination a:hover {
    background-color: #e8f3fe;
    border-color: #90caf9;
}

.admin-goods-pagination span {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

/* レスポンシブ */
@media (max-width: 768px) {
    .admin-main-content {
        padding: 20px 16px;
    }
    .admin-goods-table {
        min-width: 700px;
    }
    .admin-main-content > .admin-page-title {
        font-size: 20px;
    }
    .admin-goods-filters {
        flex-direction: column;
        align-items: stretch;
    }
    .admin-goods-filters input[type="search"],
    .admin-goods-filters select {
        min-width: auto;
        width: 100%;
    }
    .admin-goods-filters button[type="submit"],
    .admin-goods-filters a {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-container admin-container">
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1 class="admin-page-title">商品管理</h1>

    <nav class="admin-breadcrumb">
        <a href="index.php">ホーム</a>
        <span class="admin-breadcrumb-separator">›</span>
        <span>商品管理</span>
    </nav>

    <form id="goods-filter-form" class="admin-goods-filters" method="get" action="goods-list.php">
        <label>
            キーワード（商品名・ID）
            <input type="search" name="keyword" maxlength="100" value="<?= $escape($keyword) ?>">
        </label>
        <label>
            公開状態
            <select name="is_active">
                <option value="">すべて</option>
                <option value="1" <?= $is_active_filter === '1' ? 'selected' : '' ?>>公開中</option>
                <option value="0" <?= $is_active_filter === '0' ? 'selected' : '' ?>>公開停止</option>
            </select>
        </label>
        <label>
            販売状態
            <select name="is_sold">
                <option value="">すべて</option>
                <option value="0" <?= $is_sold_filter === '0' ? 'selected' : '' ?>>販売中</option>
                <option value="1" <?= $is_sold_filter === '1' ? 'selected' : '' ?>>売却済み</option>
            </select>
        </label>
        <button type="button" id="goods-filter-reset" class="admin-goods-reset-btn">条件をクリア</button>
    </form>

    <div id="goods-results">
    <?php if (!empty($goods_list)): ?>
        <p class="admin-goods-count"><?= number_format($total_count) ?>件</p>
        <table class="admin-goods-table">
            <thead>
                <tr>
                    <th>画像</th>
                    <th>商品ID</th>
                    <th>商品名</th>
                    <th>出品者</th>
                    <th>価格</th>
                    <th>出品日時</th>
                    <th>販売状態</th>
                    <th>公開状態</th>
                    <th>オークション状態</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($goods_list as $goods): ?>
                    <tr>
                        <td>
                            <?php
                            $image_path = $goods['image_path'] ?? '';
                            $is_valid_image = is_string($image_path)
                                && preg_match('~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i', $image_path)
                                && is_file(__DIR__ . '/../../' . $image_path);
                            ?>
                            <?php if ($is_valid_image): ?>
                                <img
                                    src="/Meetory/<?= $escape($image_path) ?>"
                                    alt=""
                                    class="admin-goods-thumb"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div class="admin-goods-thumb" style="background:#e2e8f0;"></div>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $goods['goods_id'] ?></td>
                        <td><?= $escape($goods['goods_name']) ?></td>
                        <td><?= $escape($goods['seller_name']) ?></td>
                        <td><?= number_format((int) $goods['price']) ?>円</td>
                        <td><?= $escape($goods['listed_at']) ?></td>
                        <td>
                            <span class="admin-goods-badge <?= (int) $goods['is_sold'] === 1 ? 'admin-goods-badge-sold' : 'admin-goods-badge-active' ?>">
                                <?= (int) $goods['is_sold'] === 1 ? '売却済み' : '販売中' ?>
                            </span>
                        </td>
                        <td>
                            <span class="admin-goods-badge <?= (int) $goods['is_active'] === 1 ? 'admin-goods-badge-active' : 'admin-goods-badge-inactive' ?>">
                                <?= (int) $goods['is_active'] === 1 ? '公開中' : '公開停止' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($goods['auction_status'])): ?>
                                <span class="admin-goods-badge admin-goods-badge-auction">
                                    <?= $escape($goods['auction_status']) ?>
                                </span>
                            <?php else: ?>
                                <span>-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>">詳細</a>
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
            <nav class="admin-goods-pagination" aria-label="ページ送り">
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
        <p>該当する商品はありません。</p>
    <?php endif; ?>
    </div>

        </div>
    </div>
</main>

<script>
(function() {
    'use strict';

    var form = document.getElementById('goods-filter-form');
    var resetBtn = document.getElementById('goods-filter-reset');
    var resultsContainer = document.getElementById('goods-results');
    var timer = null;
    var controller = null;

    if (!form || !resultsContainer) {
        return;
    }

    function buildQueryString() {
        var params = new URLSearchParams();
        var keywordInput = form.querySelector('input[name="keyword"]');
        var isActiveSelect = form.querySelector('select[name="is_active"]');
        var isSoldSelect = form.querySelector('select[name="is_sold"]');

        if (keywordInput && keywordInput.value !== '') {
            params.append('keyword', keywordInput.value);
        }
        if (isActiveSelect && isActiveSelect.value !== '') {
            params.append('is_active', isActiveSelect.value);
        }
        if (isSoldSelect && isSoldSelect.value !== '') {
            params.append('is_sold', isSoldSelect.value);
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
                resultsContainer.innerHTML = '<p>検索結果の取得に失敗しました。</p>';
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
        if (event.target.tagName === 'SELECT') {
            debounceFetch();
        }
    });

    resetBtn.addEventListener('click', function() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        if (controller) {
            controller.abort();
            controller = null;
        }
        var keywordInput = form.querySelector('input[name="keyword"]');
        var isActiveSelect = form.querySelector('select[name="is_active"]');
        var isSoldSelect = form.querySelector('select[name="is_sold"]');

        if (keywordInput) keywordInput.value = '';
        if (isActiveSelect) isActiveSelect.selectedIndex = 0;
        if (isSoldSelect) isSoldSelect.selectedIndex = 0;

        fetchResults();
    });
})();
</script>

<?php require '../../meetory-footer.php'; ?>
