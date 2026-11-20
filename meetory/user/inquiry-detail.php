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
$raw_inquiry_id = $_GET['inquiry_id'] ?? null;
$inquiry_id = is_string($raw_inquiry_id)
    ? filter_var($raw_inquiry_id, FILTER_VALIDATE_INT)
    : false;
$inquiry = null;
$messages = [];
$load_error = false;
$created_notice = false;

if ($inquiry_id === false || $inquiry_id === null || $inquiry_id <= 0) {
    http_response_code(404);
} else {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT inquiry_id, category, subject, message, status, created_at, updated_at
             FROM inquiries
             WHERE inquiry_id = :inquiry_id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'inquiry_id' => $inquiry_id,
            'user_id' => $user_id
        ]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inquiry === false) {
            $inquiry = null;
            http_response_code(404);
        } else {
            $message_stmt = $pdo->prepare(
                'SELECT message_id, sender_type, message, created_at
                 FROM inquiry_messages
                 WHERE inquiry_id = :inquiry_id
                 ORDER BY created_at ASC, message_id ASC'
            );
            $message_stmt->execute(['inquiry_id' => $inquiry_id]);
            $messages = $message_stmt->fetchAll(PDO::FETCH_ASSOC);
            $created_notice = isset($_SESSION['created_inquiry_id'])
                && (int) $_SESSION['created_inquiry_id'] === (int) $inquiry_id;
            if ($created_notice) {
                unset($_SESSION['created_inquiry_id']);
            }
        }
    } catch (PDOException $e) {
        error_log('inquiry detail load failed: ' . $e->getMessage());
        $load_error = true;
    }
}

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/inquiries.css">

<div class="inquiry-page">
    <?php if ($load_error): ?>
        <h1>お問い合わせ詳細</h1>
        <p class="inquiry-alert" role="alert">お問い合わせを読み込めませんでした。時間をおいて再度お試しください。</p>
    <?php elseif ($inquiry === null): ?>
        <h1>お問い合わせが見つかりません</h1>
        <p>指定されたお問い合わせを表示できません。</p>
    <?php else: ?>
        <?php if ($created_notice): ?>
            <p class="inquiry-success" role="status">
                お問い合わせを受け付けました。問い合わせ番号は #<?= (int) $inquiry['inquiry_id'] ?> です。
            </p>
        <?php endif; ?>

        <h1>お問い合わせ詳細 #<?= (int) $inquiry['inquiry_id'] ?></h1>
        <dl class="inquiry-details">
            <dt>カテゴリ</dt><dd><?= $escape($inquiry['category']) ?></dd>
            <dt>件名</dt><dd><?= $escape($inquiry['subject']) ?></dd>
            <dt>送信日時</dt><dd><?= $escape($inquiry['created_at']) ?></dd>
            <dt>対応状況</dt><dd><span class="inquiry-status"><?= $escape($inquiry['status']) ?></span></dd>
            <dt>最終更新日時</dt><dd><?= $escape($inquiry['updated_at']) ?></dd>
        </dl>

        <section class="inquiry-message">
            <h2>お問い合わせ内容</h2>
            <p><?= nl2br($escape($inquiry['message'])) ?></p>
            <time><?= $escape($inquiry['created_at']) ?></time>
        </section>

        <section class="inquiry-conversation">
            <h2>返信・対応履歴</h2>
            <?php if (empty($messages)): ?>
                <p>運営からの返信はまだありません。</p>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <article class="inquiry-message">
                        <h3><?= $message['sender_type'] === 'admin' ? '運営からの返信' : 'あなたのメッセージ' ?></h3>
                        <p><?= nl2br($escape($message['message'])) ?></p>
                        <time><?= $escape($message['created_at']) ?></time>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <p><a href="my-inquiries.php">お問い合わせ一覧に戻る</a></p>
</div>

<?php require '../../meetory-footer.php'; ?>
