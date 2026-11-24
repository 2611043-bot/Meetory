<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../../user/login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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