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

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (
    !isset($_SESSION['contact_submission_tokens']) ||
    !is_array($_SESSION['contact_submission_tokens'])
) {
    $_SESSION['contact_submission_tokens'] = [];
}
$submission_token = bin2hex(random_bytes(32));
$_SESSION['contact_submission_tokens'][$submission_token] = true;
if (count($_SESSION['contact_submission_tokens']) > 10) {
    $_SESSION['contact_submission_tokens'] = array_slice(
        $_SESSION['contact_submission_tokens'],
        -10,
        null,
        true
    );
}

$form_data = $_SESSION['contact_form_data'] ?? [];
$errors = $_SESSION['contact_form_errors'] ?? [];
unset($_SESSION['contact_form_data'], $_SESSION['contact_form_errors']);

if (!is_array($form_data)) {
    $form_data = [];
}
if (!is_array($errors)) {
    $errors = [];
}

$category_value = $form_data['category'] ?? '';
$subject_value = $form_data['subject'] ?? '';
$message_value = $form_data['message'] ?? '';
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/inquiries.css">

<div class="inquiry-page">
    <h1>お問い合わせ</h1>
    <p>内容を確認のうえ、運営から返信します。</p>

    <?php if (!empty($errors)): ?>
        <div class="inquiry-alert" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= $escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="inquiry-form" action="contact-process.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="submission_token" value="<?= $escape($submission_token) ?>">

        <label for="category">お問い合わせカテゴリ</label>
        <select id="category" name="category" required>
            <option value="">選択してください</option>
            <?php foreach (getInquiryCategories() as $category): ?>
                <option value="<?= $escape($category) ?>" <?= $category_value === $category ? 'selected' : '' ?>>
                    <?= $escape($category) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="subject">件名（200文字以内）</label>
        <input
            id="subject"
            name="subject"
            type="text"
            maxlength="200"
            value="<?= $escape($subject_value) ?>"
            required
        >

        <label for="message">お問い合わせ内容（10,000文字以内）</label>
        <textarea id="message" name="message" rows="9" maxlength="10000" required><?= $escape($message_value) ?></textarea>

        <button type="submit">お問い合わせを送信</button>
    </form>

    <p><a href="my-inquiries.php">自分のお問い合わせ一覧</a></p>
</div>

<?php require '../../meetory-footer.php'; ?>
