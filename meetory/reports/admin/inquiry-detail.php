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

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$raw_inquiry_id = $_GET['inquiry_id'] ?? null;
$inquiry_id = is_string($raw_inquiry_id)
    ? filter_var($raw_inquiry_id, FILTER_VALIDATE_INT)
    : false;
$inquiry = null;
$messages = [];
$load_error = false;

if ($inquiry_id === false || $inquiry_id === null || $inquiry_id <= 0) {
    http_response_code(404);
} else {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT
                i.inquiry_id,
                i.user_id,
                i.category,
                i.subject,
                i.message,
                i.status,
                i.created_at,
                i.updated_at,
                u.user_name
             FROM inquiries AS i
             INNER JOIN users AS u ON u.user_id = i.user_id
             WHERE i.inquiry_id = :inquiry_id
             LIMIT 1'
        );
        $stmt->execute(['inquiry_id' => $inquiry_id]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inquiry === false) {
            $inquiry = null;
            http_response_code(404);
        } else {
            $message_stmt = $pdo->prepare(
                'SELECT
                    im.message_id,
                    im.sender_type,
                    im.message,
                    im.created_at,
                    u.user_name
                 FROM inquiry_messages AS im
                 INNER JOIN users AS u ON u.user_id = im.sender_id
                 WHERE im.inquiry_id = :inquiry_id
                 ORDER BY im.created_at ASC, im.message_id ASC'
            );
            $message_stmt->execute(['inquiry_id' => $inquiry_id]);
            $messages = $message_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('admin inquiry detail load failed: ' . $e->getMessage());
        $load_error = true;
    }
}

$flash = $_SESSION['inquiry_admin_flash'] ?? '';
unset($_SESSION['inquiry_admin_flash']);
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

require '../../../meetory-header.php';
?>

<link rel="stylesheet" href="../../assets/css/inquiries.css">

<div class="inquiry-page">
    <?php if ($load_error): ?>
        <h1>お問い合わせ詳細</h1>
        <p class="inquiry-alert" role="alert">お問い合わせを読み込めませんでした。時間をおいて再度お試しください。</p>
    <?php elseif ($inquiry === null): ?>
        <h1>お問い合わせが見つかりません</h1>
        <p>指定されたお問い合わせを表示できません。</p>
    <?php else: ?>
        <h1>お問い合わせ詳細 #<?= (int) $inquiry['inquiry_id'] ?></h1>
        <?php if (is_string($flash) && $flash !== ''): ?>
            <p class="inquiry-success" role="status"><?= $escape($flash) ?></p>
        <?php endif; ?>

        <dl class="inquiry-details">
            <dt>ユーザー</dt><dd><?= $escape($inquiry['user_name']) ?>（ID: <?= (int) $inquiry['user_id'] ?>）</dd>
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
            <h2>対応履歴</h2>
            <?php if (empty($messages)): ?>
                <p>返信履歴はありません。</p>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <article class="inquiry-message">
                        <h3>
                            <?= $message['sender_type'] === 'admin' ? '管理者からの返信' : 'ユーザーからの追加メッセージ' ?>
                            （<?= $escape($message['user_name']) ?>）
                        </h3>
                        <p><?= nl2br($escape($message['message'])) ?></p>
                        <time><?= $escape($message['created_at']) ?></time>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="inquiry-message">
            <h2>対応状況を変更</h2>
            <form class="inquiry-form" action="inquiry-process.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="inquiry_id" value="<?= (int) $inquiry['inquiry_id'] ?>">
                <input type="hidden" name="action" value="change_status">
                <label for="status">対応状況</label>
                <select id="status" name="status" required>
                    <?php foreach (getInquiryStatuses() as $status => $label): ?>
                        <option value="<?= $escape($status) ?>" <?= $inquiry['status'] === $status ? 'selected' : '' ?>>
                            <?= $escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">状況を保存</button>
            </form>
        </section>

        <section class="inquiry-message">
            <h2>返信を送信</h2>
            <form class="inquiry-form" action="inquiry-process.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="inquiry_id" value="<?= (int) $inquiry['inquiry_id'] ?>">
                <input type="hidden" name="action" value="reply">
                <label for="reply">返信内容（10,000文字以内）</label>
                <textarea id="reply" name="message" rows="8" maxlength="10000" required></textarea>
                <label>
                    <input type="checkbox" name="mark_answered" value="1" checked>
                    返信後に対応状況を「回答済み」にする
                </label>
                <button type="submit">返信を送信</button>
            </form>
        </section>
    <?php endif; ?>

    <p><a href="inquiries.php">お問い合わせ一覧に戻る</a></p>
</div>

<?php require '../../../meetory-footer.php'; ?>
