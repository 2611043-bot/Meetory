<?php

function getPDO()
{
    $config = require __DIR__ . '/db-config.php';

    return new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['password']
    );
}

function getProfileAvatarPresetIds(): array
{
    return [
        'preset-01',
        'preset-02',
        'preset-03',
        'preset-04',
        'preset-05',
        'preset-06',
        'preset-07',
        'preset-08'
    ];
}

function getProfileAvatarUrl(array $user): string
{
    $preset_ids = getProfileAvatarPresetIds();

    $image_type = $user['profile_image_type'] ?? '';
    $image_name = $user['profile_image'] ?? '';

    if (
        $image_type === 'preset' &&
        is_string($image_name) &&
        in_array($image_name, $preset_ids, true)
    ) {
        $preset_path = __DIR__ . '/meetory/assets/images/profile-icons/' .
            $image_name . '.svg';
        if (is_file($preset_path) && is_readable($preset_path)) {
            return '/Meetory/meetory/assets/images/profile-icons/' . $image_name . '.svg';
        }
    }

    if ($image_type === 'upload' && getProfileUploadPath($image_name) !== null) {
        return '/Meetory/uploads/profile/' . rawurlencode($image_name);
    }

    return '/Meetory/meetory/assets/images/profile-icons/preset-01.svg';
}

function getProfileUploadPath($image_name): ?string
{
    if (
        !is_string($image_name) ||
        !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/i', $image_name)
    ) {
        return null;
    }

    $path = __DIR__ . '/uploads/profile/' . $image_name;

    return is_file($path) && is_readable($path) ? $path : null;
}

function renderProfileAvatar(
    array $user,
    string $class = '',
    string $alt = ''
): string {
    $classes = trim('user-avatar-image ' . $class);

    return '<img class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') .
        '" src="' . htmlspecialchars(getProfileAvatarUrl($user), ENT_QUOTES, 'UTF-8') .
        '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
}

function data() {
    // 新規登録画面から送られてきた情報を受け取る
    $user_name = 'user_name';                 // ユーザー名
    $email = 'email';                         // メールアドレス
    $password = 'password';                   // パスワード
    $password_confirm = 'password_confirm';   // パスワード（確認）
}

?>