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

function ensureNotificationSettingsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_settings (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sound_enabled TINYINT(1) NOT NULL DEFAULT 1,
            type_message TINYINT(1) NOT NULL DEFAULT 1,
            type_purchase TINYINT(1) NOT NULL DEFAULT 1,
            type_shipping TINYINT(1) NOT NULL DEFAULT 1,
            type_received TINYINT(1) NOT NULL DEFAULT 1,
            type_review TINYINT(1) NOT NULL DEFAULT 1,
            type_favorite TINYINT(1) NOT NULL DEFAULT 1,
            type_price_down TINYINT(1) NOT NULL DEFAULT 1,
            type_follow TINYINT(1) NOT NULL DEFAULT 1,
            type_admin TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function getNotificationSettings(PDO $pdo, int $user_id): array
{
    ensureNotificationSettingsTable($pdo);

    $stmt = $pdo->prepare('SELECT * FROM notification_settings WHERE user_id = :user_id LIMIT 1');
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        $defaultSettings = [
            'user_id'         => $user_id,
            'enabled'         => 1,
            'sound_enabled'   => 1,
            'type_message'    => 1,
            'type_purchase'   => 1,
            'type_shipping'   => 1,
            'type_received'   => 1,
            'type_review'     => 1,
            'type_favorite'   => 1,
            'type_price_down' => 1,
            'type_follow'     => 1,
            'type_admin'      => 1,
        ];
        $cols = implode(', ', array_keys($defaultSettings));
        $placeholders = ':' . implode(', :', array_keys($defaultSettings));
        $stmt = $pdo->prepare("INSERT INTO notification_settings ($cols) VALUES ($placeholders)");
        $stmt->execute($defaultSettings);
        return $defaultSettings;
    }

    return $row;
}

function saveNotificationSettings(PDO $pdo, int $user_id, array $data): bool
{
    ensureNotificationSettingsTable($pdo);

    $allowed = ['enabled', 'sound_enabled', 'type_message', 'type_purchase', 'type_shipping', 'type_received', 'type_review', 'type_favorite', 'type_price_down', 'type_follow', 'type_admin'];
    $updateData = ['user_id' => $user_id];
    foreach ($allowed as $key) {
        $updateData[$key] = isset($data[$key]) ? 1 : 0;
    }

    $setClause = implode(', ', array_map(fn($k) => "$k = :$k", array_filter(array_keys($updateData), fn($k) => $k !== 'user_id')));
    $sql = "UPDATE notification_settings SET $setClause WHERE user_id = :user_id";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($updateData);
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

?>