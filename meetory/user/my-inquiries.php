<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) or
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false or
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

require_once '../../meetory-functions.php';

$user_id = (int) $_SESSION['user_id'];$per_page = 20;
$page_value = $_GET['page'] ?? '1';$page = is_string($page_value) ? filter_var($page_value, FILTER_VALIDATE_INT) : false;
$page = $page !== false &&$page !== null && $page > 0 ?$page : 1;
$inquiries = [];$total_count = 0;
$load_error = false;

try {
    $pdo = getPDO();
    $count_stmt =$pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE user_id = :user_id');
    $count_stmt->execute(['user_id' =>$user_id]);
    $total_count = (int)$count_stmt->fetchColumn();

    $total_pages = max(1, (int) ceil($total_count / $per_page));$page = min($page,$total_pages);
    $stmt =$pdo->prepare(
        'SELECT inquiry_id, category, subject, status, created_at, updated_at
         FROM inquiries
         WHERE user_id = :user_id
         ORDER BY updated_at DESC, inquiry_id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id',$user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit',$per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $per_page, PDO::PARAM_INT);$stmt->execute();
    $inquiries =$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('inquiry list load failed: ' . $e->getMessage());$load_error = true;
    $total_pages = 1;
}

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };$status_classes = [
    '未対応' => 'pending',
    '対応中' => 'in-progress',
    '回答済み' => 'answered',
    '完了' => 'complete'
];

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/my-inquiries.css?v=<?= time() ?>">

<div class="inquiry-page">
    <h1>お問い合わせ一覧</h1>
    <p><a class="inquiry-new-link" href="contact.php">新しいお問い合わせを送信</a></p>

    <?php if ($load_error): ?>
        <p class="inquiry-alert" role="alert">お問い合わせを読み込めませんでした。時間をおいて再度お試しください。</p>
    <?php elseif (empty($inquiries)): ?>
        <p>お問い合わせ履歴はありません。</p>
    <?php else: ?>
        <div class="inquiry-card-list">
            <?php foreach ($inquiries as$inquiry): ?>
                <?php
                $inquiry_number = (int) $inquiry['inquiry_id'];$status_class = $status_classes[$inquiry['status']] ?? 'unknown';
                ?>
                <a
                    class="inquiry-card"
                    href="inquiry-detail.php?inquiry_id=<?= $inquiry_number ?>"
                    aria-label="<?= $escape('問い合わせ番号 #' . $inquiry_number . '、' . $inquiry['subject'] . ' の詳細を見る') ?>"
                >
                    <div class="inquiry-card-top">
                        <span class="inquiry-card-number">問い合わせ番号 #<?= $inquiry_number ?></span>
                        <span class="inquiry-status inquiry-status--<?= $escape($status_class) ?>">
                            <?= $escape($inquiry['status']) ?>
                        </span>
                    </div>
                    <h2 class="inquiry-card-title"><?= $escape($inquiry['subject']) ?></h2>
                    <dl class="inquiry-card-meta">
                        <div>
                            <dt>カテゴリ</dt>
                            <dd><?= $escape($inquiry['category']) ?></dd>
                        </div>
                        <div>
                            <dt>送信日時</dt>
                            <dd><?= $escape($inquiry['created_at']) ?></dd>
                        </div>
                        <div>
                            <dt>最終更新日時</dt>
                            <dd><?= $escape($inquiry['updated_at']) ?></dd>
                        </div>
                    </dl>
                    <span class="inquiry-card-action">問い合わせ詳細 →</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="inquiry-pagination" aria-label="ページ送り">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">前へ</a>
                <?php endif; ?>
                <span><?= $page ?> / <?= $total_pages ?></span>
                <?php if ($page <$total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">次へ</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require '../../meetory-footer.php'; ?>