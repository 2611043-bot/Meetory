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

$redirect_with_error = static function (string $message): void {
    $_SESSION['profile_image_error'] = $message;
    header('Location: profile-image.php');
    exit;
};

$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    http_response_code(403);
    exit('不正なリクエストです。');
}

$pdo = getPDO();
$stmt = $pdo->prepare(
    'SELECT profile_image_type, profile_image
    FROM users
    WHERE user_id = :user_id'
);
$stmt->execute(['user_id' => $user_id]);
$old_image = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$old_image) {
    http_response_code(404);
    exit('ユーザーが見つかりません。');
}

$presets = getProfileAvatarPresetIds();
$choice = $_POST['profile_image_choice'] ?? '';
$new_type = '';
$new_image = '';
$new_file_path = null;

if (is_string($choice) && in_array($choice, $presets, true)) {
    $new_type = 'preset';
    $new_image = $choice;
} elseif ($choice === 'upload') {
    $upload = $_FILES['profile_upload'] ?? null;

    if (!is_array($upload) || !isset($upload['error'])) {
        $redirect_with_error('画像ファイルを選択してください。');
    }

    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        if (
            $old_image['profile_image_type'] === 'upload' &&
            getProfileUploadPath($old_image['profile_image']) !== null
        ) {
            header('Location: profile-image.php?saved=1');
            exit;
        }

        $redirect_with_error('画像ファイルを選択してください。');
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $message = $upload['error'] === UPLOAD_ERR_INI_SIZE
            || $upload['error'] === UPLOAD_ERR_FORM_SIZE
            ? '画像は5MB以下にしてください。'
            : '画像のアップロードに失敗しました。もう一度お試しください。';
        $redirect_with_error($message);
    }

    if (
        !isset($upload['tmp_name'], $upload['name'], $upload['size']) ||
        !is_string($upload['tmp_name']) ||
        !is_string($upload['name']) ||
        !is_uploaded_file($upload['tmp_name'])
    ) {
        $redirect_with_error('アップロードされた画像を確認できませんでした。');
    }

    $actual_size = filesize($upload['tmp_name']);
    if ($actual_size === false || $actual_size <= 0 || $actual_size > 5 * 1024 * 1024) {
        $redirect_with_error('画像は5MB以下にしてください。');
    }

    $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    $extension_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];

    if (!isset($extension_mimes[$extension])) {
        $redirect_with_error('JPG・PNG・WebP形式の画像を選択してください。');
    }

    if (!function_exists('finfo_open')) {
        $redirect_with_error('画像形式を確認できませんでした。');
    }

    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    if ($file_info === false) {
        $redirect_with_error('画像形式を確認できませんでした。');
    }

    $mime_type = finfo_file($file_info, $upload['tmp_name']);
    finfo_close($file_info);

    $image_info = @getimagesize($upload['tmp_name']);
    if (
        $mime_type !== $extension_mimes[$extension] ||
        $image_info === false ||
        ($image_info[0] ?? 0) <= 0 ||
        ($image_info[1] ?? 0) <= 0 ||
        ($image_info['mime'] ?? '') !== $mime_type
    ) {
        $redirect_with_error('ファイルの拡張子と画像形式が一致しません。');
    }

    $stored_extension = $mime_type === 'image/jpeg'
        ? 'jpg'
        : ($mime_type === 'image/png' ? 'png' : 'webp');
    $new_image = bin2hex(random_bytes(16)) . '.' . $stored_extension;
    $upload_directory = __DIR__ . '/../../uploads/profile';

    if (
        !is_dir($upload_directory) &&
        !mkdir($upload_directory, 0755, true) &&
        !is_dir($upload_directory)
    ) {
        $redirect_with_error('画像の保存先を準備できませんでした。');
    }

    $new_file_path = $upload_directory . '/' . $new_image;
    if (!move_uploaded_file($upload['tmp_name'], $new_file_path)) {
        $redirect_with_error('画像を保存できませんでした。もう一度お試しください。');
    }

    $new_type = 'upload';
} else {
    $redirect_with_error('選択されたアイコンが正しくありません。');
}

try {
    $update_stmt = $pdo->prepare(
        'UPDATE users
        SET profile_image_type = :profile_image_type,
            profile_image = :profile_image
        WHERE user_id = :user_id'
    );
    $update_stmt->execute([
        'profile_image_type' => $new_type,
        'profile_image' => $new_image,
        'user_id' => $user_id
    ]);
} catch (PDOException $exception) {
    if (
        $new_file_path !== null &&
        is_file($new_file_path) &&
        !unlink($new_file_path)
    ) {
        error_log('Unable to remove the uncommitted profile image: ' . $new_file_path);
    }

    error_log('Profile image update failed: ' . $exception->getMessage());
    $redirect_with_error('アイコンを保存できませんでした。時間をおいて再度お試しください。');
}

if (
    $old_image['profile_image_type'] === 'upload' &&
    $old_image['profile_image'] !== $new_image
) {
    $old_file_path = getProfileUploadPath($old_image['profile_image']);
    if ($old_file_path !== null && !unlink($old_file_path)) {
        error_log('Unable to remove the previous profile image: ' . $old_file_path);
    }
}

header('Location: profile-image.php?saved=1');
exit;
