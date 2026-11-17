<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../meetory-functions.php';

$user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
if ($user_id === false || $user_id === null || $user_id <= 0) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();

$typeColumns = [
    'message'      => 'type_message',
    'purchase'     => 'type_purchase',
    'shipping'     => 'type_shipping',
    'received'     => 'type_received',
    'review'       => 'type_review',
    'favorite'     => 'type_favorite',
    'price_down'   => 'type_price_down',
    'follow'       => 'type_follow',
    'admin'        => 'type_admin',
];

$typeLabels = [
    'message'    => 'メッセージ',
    'purchase'   => '購入',
    'shipping'   => '発送',
    'received'   => '受取',
    'review'     => '評価',
    'favorite'   => 'お気に入り',
    'price_down' => '値下げ',
    'follow'     => 'フォロー',
    'admin'      => '運営からのお知らせ',
];

$csrf_token = $_SESSION['csrf_token'] ?? '';
if ($csrf_token === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf_token = $_SESSION['csrf_token'];
}

$flash_message = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (
        isset($_SESSION['csrf_token']) &&
        is_string($_SESSION['csrf_token']) &&
        $_SESSION['csrf_token'] !== '' &&
        is_string($post_csrf) &&
        hash_equals($_SESSION['csrf_token'], $post_csrf)
    ) {
        $success = saveNotificationSettings($pdo, $user_id, $_POST);
        if ($success) {
            $flash_message = '通知設定を保存しました。';
            $flash_type = 'success';
        } else {
            $flash_message = '設定の保存に失敗しました。時間をおいて再度お試しください。';
            $flash_type = 'error';
        }
    } else {
        $flash_message = '不正なリクエストです。ページを開き直してください。';
        $flash_type = 'error';
    }
}

$settings = getNotificationSettings($pdo, $user_id);

require '../../meetory-header.php';
?>

<link
    rel="stylesheet"
    href="../assets/css/notification-settings.css?v=<?= filemtime(__DIR__ . '/../assets/css/notification-settings.css') ?>"
>

<main class="notification-settings-page">
    <header class="notification-settings-heading">
        <h1>通知設定</h1>
        <p>受け取る通知の種類と通知音を設定できます。</p>
    </header>

    <?php if ($flash_message !== ''): ?>
        <div class="flash-message flash-message-<?= htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flash_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form action="notification-settings.php" method="post" class="notification-settings-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <section class="settings-section">
            <h2>全体設定</h2>
            <div class="setting-item">
                <div class="setting-info">
                    <h3>通知を受け取る</h3>
                    <p>すべての通知を一括でON/OFFします。OFFにすると通知一覧に表示されず、未読数も0になります。</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="enabled" value="1" <?= (int)$settings['enabled'] === 1 ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <section class="settings-section">
            <h2>種類別設定</h2>
            <p class="settings-section-desc">個別に通知を受け取るかどうかを設定できます。「通知を受け取る」がOFFの場合、この設定に関わらず通知は届きません。</p>
            <?php foreach ($typeColumns as $typeKey => $colName): ?>
                <div class="setting-item">
                    <div class="setting-info">
                        <h3><?= htmlspecialchars($typeLabels[$typeKey], ENT_QUOTES, 'UTF-8') ?></h3>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="<?= htmlspecialchars($colName, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= (int)$settings[$colName] === 1 ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="settings-section">
            <h2>通知音</h2>
            <div class="setting-item">
                <div class="setting-info">
                    <h3>通知音を再生する</h3>
                    <p>新着通知があったときに音を鳴らします。通知一覧や未読数には影響しません。</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="sound_enabled" value="1" <?= (int)$settings['sound_enabled'] === 1 ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <div class="settings-actions">
            <button type="submit" class="btn btn-primary">保存する</button>
            <a href="mypage.php" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</main>

<?php require '../../meetory-footer.php'; ?>