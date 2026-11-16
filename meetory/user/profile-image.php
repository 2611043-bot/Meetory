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
$stmt = $pdo->prepare(
    'SELECT user_name, profile_image_type, profile_image
    FROM users
    WHERE user_id = :user_id'
);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    exit('ユーザーが見つかりません。');
}

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$presets = getProfileAvatarPresetIds();
$current_preset = $user['profile_image_type'] === 'preset'
    && in_array($user['profile_image'], $presets, true)
    ? $user['profile_image']
    : 'preset-01';
$using_upload = $user['profile_image_type'] === 'upload';
$error_message = $_SESSION['profile_image_error'] ?? '';
unset($_SESSION['profile_image_error']);

require '../../meetory-header.php';
?>

<link
    rel="stylesheet"
    href="../assets/css/profile-image.css?v=<?= filemtime(__DIR__ . '/../assets/css/profile-image.css') ?>"
>

<main class="profile-image-page">
    <header class="profile-image-heading">
        <h1>プロフィールアイコン</h1>
        <p>アイコンを選択するか、お手持ちの画像をアップロードできます。</p>
    </header>

    <?php if ($error_message !== ''): ?>
        <p class="profile-image-alert" role="alert">
            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php elseif (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
        <p class="profile-image-success" role="status">アイコンを保存しました。</p>
    <?php endif; ?>

    <section class="profile-image-card">
        <div class="profile-image-current">
            <p>現在のアイコン</p>
            <div class="profile-image-preview" id="profileImagePreview">
                <?= renderProfileAvatar(
                    $user,
                    'profile-image-preview-img',
                    $user['user_name'] . 'のプロフィールアイコン'
                ) ?>
            </div>
            <strong><?= htmlspecialchars($user['user_name'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <form
            action="profile-image-save.php"
            method="post"
            enctype="multipart/form-data"
            id="profileImageForm"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <fieldset class="profile-image-presets">
                <legend>初期アイコンから選ぶ</legend>
                <div class="profile-image-grid">
                    <?php foreach ($presets as $preset): ?>
                        <label class="profile-image-option">
                            <input
                                type="radio"
                                name="profile_image_choice"
                                value="<?= htmlspecialchars($preset, ENT_QUOTES, 'UTF-8') ?>"
                                data-image-url="/Meetory/meetory/assets/images/profile-icons/<?= htmlspecialchars($preset, ENT_QUOTES, 'UTF-8') ?>.svg"
                                <?= !$using_upload && $current_preset === $preset ? 'checked' : '' ?>
                            >
                            <img
                                src="/Meetory/meetory/assets/images/profile-icons/<?= htmlspecialchars($preset, ENT_QUOTES, 'UTF-8') ?>.svg"
                                alt=""
                                width="64"
                                height="64"
                            >
                            <span>アイコン<?= (int) substr($preset, -2) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="profile-image-upload">
                <legend>自分の画像を使う</legend>
                <label class="profile-image-upload-choice">
                    <input
                        type="radio"
                        name="profile_image_choice"
                        value="upload"
                        data-image-url="<?= htmlspecialchars(getProfileAvatarUrl($user), ENT_QUOTES, 'UTF-8') ?>"
                        <?= $using_upload ? 'checked' : '' ?>
                    >
                    <span>アップロード画像を選択</span>
                </label>
                <label for="profileUpload">画像ファイル</label>
                <input
                    type="file"
                    id="profileUpload"
                    name="profile_upload"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    data-max-size="5242880"
                >
                <small>JPG・PNG・WebP、5MB以下</small>
                <span id="profileUploadError" class="profile-image-upload-error" role="alert" hidden></span>
            </fieldset>

            <div class="profile-image-actions">
                <button type="submit">保存する</button>
                <a href="mypage.php">キャンセル</a>
            </div>
        </form>
    </section>
</main>

<script src="../assets/js/profile-image.js" defer></script>
<?php require '../../meetory-footer.php'; ?>
