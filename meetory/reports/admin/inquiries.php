<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) or
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false or
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../../user/login.php');
    exit;
}

if (!isset($_SESSION['role']) or $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('このページを閲覧する権限がありません。');
}

require_once '../../../meetory-functions.php';

$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$keyword_value = $_GET['keyword'] ?? '';
$status_filter = is_string($status_filter) && isValidInquiryStatus($status_filter)
    ? $status_filter
    : '';
$category_filter = is_string($category_filter) && in_array($category_filter, getInquiryCategories(), true)
    ? $category_filter
    : '';
$keyword = is_string($keyword_value) && mb_check_encoding($keyword_value, 'UTF-8')
    ? trim($keyword_value)
    : '';
$keyword = mb_substr($keyword, 0, 100, 'UTF-8');
$filter_sql = '';
$params = [];

if ($status_filter !== '') {
    $filter_sql .= ' AND i.status = :status';
    $params['status'] = $status_filter;
}
if ($category_filter !== '') {
    $filter_sql .= ' AND i.category = :category';
    $params['category'] = $category_filter;
}
if ($keyword !== '') {
    $search_conditions = [
        'i.subject LIKE :keyword_subject',
        'u.user_name LIKE :keyword_user',
        'i.message LIKE :keyword_message'
    ];
    $like = '%' . $keyword . '%';
    $params['keyword_subject'] = $like;
    $params['keyword_user'] = $like;
    $params['keyword_message'] = $like;
    if (ctype_digit($keyword)) {
        $search_conditions[] = 'i.inquiry_id = :keyword_id';
        $params['keyword_id'] = (int) $keyword;
    }
    $filter_sql .= ' AND (' . implode(' OR ', $search_conditions) . ')';
}

$filter_params = [];
if ($status_filter !== '') {
    $filter_params['status'] = $status_filter;
}
if ($category_filter !== '') {
    $filter_params['category'] = $category_filter;
}
if ($keyword !== '') {
    $filter_params['keyword'] = $keyword;
}
$filter_query = http_build_query($filter_params);
$page_value = $_GET['page'] ?? '1';
$page = is_string($page_value) ? filter_var($page_value, FILTER_VALIDATE_INT) : false;
$page = $page !== false && $page !== null && $page > 0 ? $page : 1;
$per_page = 50;
$inquiries = [];
$total_count = 0;
$load_error = false;

try {
    $pdo = getPDO();
    $base_sql = ' FROM inquiries AS i
        INNER JOIN users AS u ON u.user_id = i.user_id
        WHERE 1 = 1' . $filter_sql;

    $count_stmt = $pdo->prepare('SELECT COUNT(*)' . $base_sql);
    $count_stmt->execute($params);
    $total_count = (int) $count_stmt->fetchColumn();

    $total_pages = max(1, (int) ceil($total_count / $per_page));
    $page = min($page, $total_pages);
    $stmt = $pdo->prepare(
        'SELECT
            i.inquiry_id,
            i.category,
            i.subject,
            i.status,
            i.created_at,
            i.updated_at,
            u.user_name
        ' . $base_sql . '
        ORDER BY (i.status = \'未対応\') DESC, i.created_at DESC, i.inquiry_id DESC
        LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $name => $value) {
        $stmt->bindValue(':' . $name, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('admin inquiry list load failed: ' . $e->getMessage());
    $load_error = true;
    $total_pages = 1;
}

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$page_suffix = $filter_query !== '' ? '&' : '';

require '../../../meetory-header.php';
?>

<link rel="stylesheet" href="../../assets/css/inquiries.css">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
/* ページ全体の背景（Meetory標準の淡いブルーグラデーション） */
html:has(.report-page.report-admin),
body:has(.report-page.report-admin) {
    background: linear-gradient(135deg, #e6f1fc 0%, #f4f8fc 52%, #ebf3fb 100%) !important;
    background-attachment: fixed !important;
}

.report-page.report-admin {
    background: transparent !important;
    padding: 24px 18px 110px;
}

.report-page.report-admin .admin-layout {
    max-width: 1140px;
    margin: 0 auto;
    gap: 22px;
    align-items: flex-start;
    background: transparent !important;
}

/* 右側メインエリア（左の管理メニューと揃えた白のフローティングカード） */
.report-page.report-admin .admin-main-content {
    flex: 1;
    min-width: 0;
    padding: 28px 32px;
    border: 1px solid rgba(25, 118, 210, 0.12);
    border-radius: 20px;
    background-color: rgba(255, 255, 255, 0.96) !important;
    box-shadow: 0 10px 30px -8px rgba(15, 41, 66, 0.08);
    box-sizing: border-box;
}

.report-page.report-admin .inquiry-page {
    max-width: none;
    margin: 0;
    padding: 0;
    background: transparent !important;
    box-shadow: none !important;
}

/* 見出し */
.report-page.report-admin .inquiry-page > h1 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 20px;
    font-size: 22px;
    font-weight: 800;
    color: #0f2942;
    letter-spacing: -0.01em;
}

.report-page.report-admin .inquiry-page > h1::before {
    content: "";
    display: inline-block;
    width: 5px;
    height: 22px;
    border-radius: 999px;
    background-color: #1976d2;
}

/* 絞り込みフィルターフォーム */
.report-page.report-admin .inquiry-admin-filters {
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

.report-page.report-admin .inquiry-admin-filters label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
}

.report-page.report-admin .inquiry-admin-filters select,
.report-page.report-admin .inquiry-admin-filters input[type="search"] {
    height: 38px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background-color: #ffffff;
    font-size: 13px;
    color: #0f172a;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.report-page.report-admin .inquiry-admin-filters select {
    min-width: 140px;
    cursor: pointer;
}

.report-page.report-admin .inquiry-admin-filters input[type="search"] {
    min-width: 210px;
}

.report-page.report-admin .inquiry-admin-filters select:focus,
.report-page.report-admin .inquiry-admin-filters input[type="search"]:focus {
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.14);
}

.report-page.report-admin .inquiry-admin-filters button[type="submit"] {
    height: 38px;
    padding: 0 20px;
    border: none;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(25, 118, 210, 0.22);
    transition: background-color 0.15s;
}

.report-page.report-admin .inquiry-admin-filters button[type="submit"]:hover {
    background-color: #1565c0;
}

.report-page.report-admin .inquiry-admin-filters a {
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
    transition: background-color 0.15s, color 0.15s;
}

.report-page.report-admin .inquiry-admin-filters a:hover {
    background-color: #f1f5f9;
    color: #0f2942;
}

/* エラーアラート・件数表示 */
.report-page.report-admin .inquiry-alert {
    margin: 0 0 16px;
    padding: 12px 16px;
    border: 1px solid #fecaca;
    border-radius: 12px;
    background-color: #fef2f2;
    color: #dc2626;
    font-size: 13px;
    font-weight: 600;
}

.report-page.report-admin .inquiry-page > p {
    margin: 0 0 12px;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
}

/* テーブルデザイン */
.report-page.report-admin .inquiry-table-wrap {
    border: 1px solid #e2ebf5;
    border-radius: 14px;
    overflow-x: auto;
    background-color: #ffffff;
}

.report-page.report-admin .inquiry-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
}

.report-page.report-admin .inquiry-table thead th {
    padding: 13px 14px;
    border: none;
    border-bottom: 1px solid #dce6f2;
    background-color: #f1f7fe;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.report-page.report-admin .inquiry-table tbody td {
    padding: 14px;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    font-size: 13px;
    vertical-align: middle;
}

.report-page.report-admin .inquiry-table tbody tr:last-child td {
    border-bottom: none;
}

.report-page.report-admin .inquiry-table tbody tr:hover td {
    background-color: #f8fbff;
}

/* 問い合わせ番号リンク */
.report-page.report-admin .inquiry-table td:first-child {
    white-space: nowrap;
}

.report-page.report-admin .inquiry-table td:first-child a {
    color: #1976d2;
    font-weight: 800;
    text-decoration: none;
}

.report-page.report-admin .inquiry-table td:first-child a:hover {
    text-decoration: underline;
}

/* 件名セル */
.report-page.report-admin .inquiry-subject {
    max-width: 240px;
    font-weight: 600;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ステータス・新着バッジ */
.report-page.report-admin .inquiry-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1565c0;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.report-page.report-admin .inquiry-table td:first-child .inquiry-status {
    margin-left: 6px;
    background-color: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}

/* ページネーション */
.report-page.report-admin .inquiry-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 22px;
}

.report-page.report-admin .inquiry-pagination a {
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

.report-page.report-admin .inquiry-pagination a:hover {
    background-color: #e8f3fe;
    border-color: #90caf9;
}

.report-page.report-admin .inquiry-pagination span {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

/* スマホ・タブレット対応 */
@media (max-width: 768px) {
    .report-page.report-admin .admin-main-content {
        padding: 20px 16px;
        border-radius: 16px;
    }

    .report-page.report-admin .inquiry-admin-filters label,
    .report-page.report-admin .inquiry-admin-filters select,
    .report-page.report-admin .inquiry-admin-filters input[type="search"] {
        width: 100%;
    }
}
</style>

<main class="report-page report-admin">
    <div class="admin-layout">
        <?php require __DIR__ . '/../../admin/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">
        <div class="inquiry-page">
    <h1>お問い合わせ管理</h1>

    <form class="inquiry-admin-filters" method="get" action="inquiries.php">
        <label>
            対応状況
            <select name="status">
                <option value="">すべて</option>
                <?php foreach (getInquiryStatuses() as $status => $label): ?>
                    <option value="<?= $escape($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                        <?= $escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            カテゴリ
            <select name="category">
                <option value="">すべて</option>
                <?php foreach (getInquiryCategories() as $category): ?>
                    <option value="<?= $escape($category) ?>" <?= $category_filter === $category ? 'selected' : '' ?>>
                        <?= $escape($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            キーワード
            <input type="search" name="keyword" maxlength="100" value="<?= $escape($keyword) ?>">
        </label>
        <button type="submit">絞り込む</button>
        <a href="inquiries.php">条件をクリア</a>
    </form>

    <?php if ($load_error): ?>
        <p class="inquiry-alert" role="alert">お問い合わせを読み込めませんでした。時間をおいて再度お試しください。</p>
    <?php elseif (empty($inquiries)): ?>
        <p>該当するお問い合わせはありません。</p>
    <?php else: ?>
        <p><?= number_format($total_count) ?>件</p>
        <div class="inquiry-table-wrap">
            <table class="inquiry-table">
                <thead>
                    <tr>
                        <th>問い合わせ番号</th>
                        <th>ユーザー</th>
                        <th>カテゴリ</th>
                        <th>件名</th>
                        <th>送信日時</th>
                        <th>対応状況</th>
                        <th>最終更新日時</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inquiries as $inquiry): ?>
                        <tr>
                            <td>
                                <a href="inquiry-detail.php?inquiry_id=<?= (int) $inquiry['inquiry_id'] ?>">
                                    #<?= (int) $inquiry['inquiry_id'] ?>
                                </a>
                                <?php if ($inquiry['status'] === '未対応'): ?>
                                    <span class="inquiry-status">新着</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $escape($inquiry['user_name']) ?></td>
                            <td><?= $escape($inquiry['category']) ?></td>
                            <td class="inquiry-subject"><?= $escape($inquiry['subject']) ?></td>
                            <td><?= $escape($inquiry['created_at']) ?></td>
                            <td><span class="inquiry-status"><?= $escape($inquiry['status']) ?></span></td>
                            <td><?= $escape($inquiry['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="inquiry-pagination" aria-label="ページ送り">
                <?php if ($page > 1): ?>
                    <a href="?<?= $escape($filter_query) ?><?= $page_suffix ?>page=<?= $page - 1 ?>">前へ</a>
                <?php endif; ?>
                <span><?= $page ?> / <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="?<?= $escape($filter_query) ?><?= $page_suffix ?>page=<?= $page + 1 ?>">次へ</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
    </div>
        </div>
    </div>
</main>

<?php require '../../../meetory-footer.php'; ?>