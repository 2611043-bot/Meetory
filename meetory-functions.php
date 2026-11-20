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
            type_auction_bid_surpassed TINYINT(1) NOT NULL DEFAULT 1,
            type_auction_won TINYINT(1) NOT NULL DEFAULT 1,
            type_auction_ending TINYINT(1) NOT NULL DEFAULT 1,
            type_auction_bid_success TINYINT(1) NOT NULL DEFAULT 1,
            type_auction_ended_seller TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 既存テーブルにも安全にカラムを追加（既存設定を初期化しない）
    $auctionTypeColumns = [
        'type_auction_bid_surpassed' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'type_auction_won'           => 'TINYINT(1) NOT NULL DEFAULT 1',
        'type_auction_ending'        => 'TINYINT(1) NOT NULL DEFAULT 1',
        'type_auction_bid_success'   => 'TINYINT(1) NOT NULL DEFAULT 1',
        'type_auction_ended_seller'  => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    foreach ($auctionTypeColumns as $colName => $colDef) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :col_name'
        );
        $stmt->execute([
            ':table_name' => 'notification_settings',
            ':col_name'   => $colName,
        ]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec(
                'ALTER TABLE notification_settings
                 ADD COLUMN ' . $colName . ' ' . $colDef
            );
        }
    }
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
            'type_auction_bid_surpassed' => 1,
            'type_auction_won'           => 1,
            'type_auction_ending'        => 1,
            'type_auction_bid_success'   => 1,
            'type_auction_ended_seller'  => 1,
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

    $allowed = ['enabled', 'sound_enabled', 'type_message', 'type_purchase', 'type_shipping', 'type_received', 'type_review', 'type_favorite', 'type_price_down', 'type_follow', 'type_admin', 'type_auction_bid_surpassed', 'type_auction_won', 'type_auction_ending', 'type_auction_bid_success', 'type_auction_ended_seller'];
    $updateData = ['user_id' => $user_id];
    foreach ($allowed as $key) {
        $updateData[$key] = (($data[$key] ?? '') === '1') ? 1 : 0;
    }

    // 既存レコードの存在を確認
    $stmtExist = $pdo->prepare('SELECT COUNT(*) FROM notification_settings WHERE user_id = :user_id');
    $stmtExist->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmtExist->execute();
    $exists = (int) $stmtExist->fetchColumn() > 0;

    if ($exists) {
        // 既存レコードを更新
        $setClause = implode(', ', array_map(fn($k) => "$k = :$k", array_filter(array_keys($updateData), fn($k) => $k !== 'user_id')));
        $sql = "UPDATE notification_settings SET $setClause WHERE user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        return (bool) $stmt->execute($updateData);
    } else {
        // 新規レコードを挿入
        $cols = implode(', ', array_keys($updateData));
        $placeholders = ':' . implode(', :', array_keys($updateData));
        $sql = "INSERT INTO notification_settings ($cols) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        return (bool) $stmt->execute($updateData);
    }
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

function isUserBlocked(PDO $pdo, int $blocker_id, int $blocked_id): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = :b AND blocked_id = :d LIMIT 1');
    $stmt->execute([':b' => $blocker_id, ':d' => $blocked_id]);
    return (bool) $stmt->fetchColumn();
}

function isBlockedEitherWay(PDO $pdo, int $user_a, int $user_b): bool
{
    return isUserBlocked($pdo, $user_a, $user_b) || isUserBlocked($pdo, $user_b, $user_a);
}

function isValidReportTargetType(string $target_type): bool
{
    return in_array($target_type, ['goods', 'user'], true);
}

function getReportGoodsReasons(): array
{
    return [
        '禁止されている商品',
        '偽物・模倣品',
        '商品説明と実物が異なる',
        '不適切な画像',
        'その他',
    ];
}

function getReportUserReasons(): array
{
    return [
        '嫌がらせ・迷惑行為',
        '詐欺・不正行為',
        '不適切なプロフィール',
        'なりすまし',
        'その他',
    ];
}

function getReportReasons(string $target_type): array
{
    return $target_type === 'user'
        ? getReportUserReasons()
        : getReportGoodsReasons();
}

function getReportStatuses(): array
{
    return [
        'pending'   => '未対応',
        'reviewing' => '確認中',
        'resolved'  => '対応済み',
        'rejected'  => '却下',
    ];
}

function getReportStatusLabel(string $status): string
{
    $statuses = getReportStatuses();
    return $statuses[$status] ?? $status;
}

function isValidReportStatus(string $status): bool
{
    return array_key_exists($status, getReportStatuses());
}

function getInquiryCategories(): array
{
    return [
        'アカウントについて',
        '商品・出品について',
        '購入・取引について',
        '支払いについて',
        'トラブル・違反報告',
        'その他',
    ];
}

function getInquiryStatuses(): array
{
    return [
        '未対応' => '未対応',
        '対応中' => '対応中',
        '回答済み' => '回答済み',
        '完了' => '完了',
    ];
}

function isValidInquiryStatus(string $status): bool
{
    return array_key_exists($status, getInquiryStatuses());
}

function getReportTargetTypeLabel(string $target_type): string
{
    return $target_type === 'user' ? 'ユーザー' : '商品';
}

// ============================================================
// アカウント停止・利用制限 共通関数
// ============================================================

const RESTRICTION_TYPE_SUSPEND = 'suspend';
const RESTRICTION_TYPE_LISTING = 'listing';
const RESTRICTION_TYPE_PURCHASE = 'purchase';
const RESTRICTION_TYPE_MESSAGE = 'message';

const RESTRICTION_TYPES = [
    RESTRICTION_TYPE_SUSPEND,
    RESTRICTION_TYPE_LISTING,
    RESTRICTION_TYPE_PURCHASE,
    RESTRICTION_TYPE_MESSAGE,
];

function isValidRestrictionType(string $type): bool
{
    return in_array($type, RESTRICTION_TYPES, true);
}

function getRestrictionTypeLabel(string $type): string
{
    $labels = [
        RESTRICTION_TYPE_SUSPEND  => 'アカウント停止',
        RESTRICTION_TYPE_LISTING  => '出品制限',
        RESTRICTION_TYPE_PURCHASE => '購入制限',
        RESTRICTION_TYPE_MESSAGE  => 'メッセージ送信制限',
    ];
    return $labels[$type] ?? $type;
}

/**
 * 現在有効な制限をすべて取得する。
 * 開始日時が現在以前かつ、終了日時が null（無期限）または未来のもののみを有効とみなす。
 * 期限が過ぎた制限は自動的に無効（解除扱い）として扱う。
 */
function getActiveRestrictions(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare("
        SELECT
            restriction_id,
            user_id,
            restriction_type,
            reason,
            starts_at,
            ends_at,
            created_by,
            created_at
        FROM account_restrictions
        WHERE user_id = :user_id
          AND status = 'active'
          AND starts_at <= NOW()
          AND (ends_at IS NULL OR ends_at > NOW())
        ORDER BY
            CASE restriction_type
                WHEN 'suspend'  THEN 1
                WHEN 'listing'  THEN 2
                WHEN 'purchase' THEN 3
                WHEN 'message'  THEN 4
                ELSE 5
            END,
            starts_at DESC,
            restriction_id DESC
    ");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 指定した種類の現在有効な制限を1件取得する。
 */
function getActiveRestrictionByType(PDO $pdo, int $user_id, string $type): ?array
{
    if (!isValidRestrictionType($type)) {
        return null;
    }
    $restrictions = getActiveRestrictions($pdo, $user_id);
    foreach ($restrictions as $restriction) {
        if ($restriction['restriction_type'] === $type) {
            return $restriction;
        }
    }
    return null;
}

/**
 * アカウント停止中かどうかを返す。停止中は制限情報を、そうでなければ null を返す。
 */
function isAccountSuspended(PDO $pdo, int $user_id): ?array
{
    return getActiveRestrictionByType($pdo, $user_id, RESTRICTION_TYPE_SUSPEND);
}

/**
 * 出品可能かどうかを返す。停止中または出品制限中は false。
 */
function canListGoods(PDO $pdo, int $user_id): bool
{
    if (isAccountSuspended($pdo, $user_id) !== null) {
        return false;
    }
    return getActiveRestrictionByType($pdo, $user_id, RESTRICTION_TYPE_LISTING) === null;
}

/**
 * 購入可能かどうかを返す。停止中または購入制限中は false。
 */
function canPurchase(PDO $pdo, int $user_id): bool
{
    if (isAccountSuspended($pdo, $user_id) !== null) {
        return false;
    }
    return getActiveRestrictionByType($pdo, $user_id, RESTRICTION_TYPE_PURCHASE) === null;
}

/**
 * メッセージ送信可能かどうかを返す。停止中またはメッセージ制限中は false。
 * 閲覧は制限しない。
 */
function canSendMessage(PDO $pdo, int $user_id): bool
{
    if (isAccountSuspended($pdo, $user_id) !== null) {
        return false;
    }
    return getActiveRestrictionByType($pdo, $user_id, RESTRICTION_TYPE_MESSAGE) === null;
}

/**
 * ユーザーに現在適用されている制限の概要を返す。
 */
function getUserRestrictionSummary(PDO $pdo, int $user_id): array
{
    $restrictions = getActiveRestrictions($pdo, $user_id);
    $summary = [
        'suspended'  => false,
        'listing'    => false,
        'purchase'   => false,
        'message'    => false,
        'restrictions' => [],
    ];
    foreach ($restrictions as $r) {
        $type = $r['restriction_type'];
        $summary[$type] = true;
        $summary['restrictions'][] = $r;
        if ($type === RESTRICTION_TYPE_SUSPEND) {
            $summary['suspended'] = true;
        }
    }
    return $summary;
}

/**
 * 制限を新規作成し、履歴にも記録する。
 * 同種類の有効な制限があれば先に解除する。
 * 作成した restriction_id を返す。
 */
function createRestriction(
    PDO $pdo,
    int $user_id,
    string $restriction_type,
    string $reason,
    ?string $starts_at,
    ?string $ends_at,
    int $created_by
): int {
    if (!isValidRestrictionType($restriction_type)) {
        throw new InvalidArgumentException('不正な制限種別です。');
    }
    if (trim($reason) === '') {
        throw new InvalidArgumentException('理由は必須です。');
    }
    if ($created_by <= 0) {
        throw new InvalidArgumentException('実行者は必須です。');
    }

    $now = date('Y-m-d H:i:s');
    if ($starts_at === null || $starts_at === '') {
        $starts_at = $now;
    }

    $pdo->beginTransaction();
    try {
        // 同種類の有効な制限を解除する（重複を防ぐ）
        $stmt = $pdo->prepare("
            UPDATE account_restrictions
            SET status = 'revoked', revoked_at = NOW(), revoked_by = :revoked_by
            WHERE user_id = :user_id
              AND restriction_type = :type
              AND status = 'active'
        ");
        $stmt->execute([
            ':revoked_by' => $created_by,
            ':user_id'    => $user_id,
            ':type'       => $restriction_type,
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO account_restrictions
            (user_id, restriction_type, reason, starts_at, ends_at, status, created_by, created_at)
            VALUES
            (:user_id, :type, :reason, :starts_at, :ends_at, 'active', :created_by, NOW())
        ");
        $stmt->execute([
            ':user_id'     => $user_id,
            ':type'        => $restriction_type,
            ':reason'      => $reason,
            ':starts_at'   => $starts_at,
            ':ends_at'     => $ends_at,
            ':created_by'  => $created_by,
        ]);
        $restriction_id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO account_restriction_logs
            (restriction_id, user_id, action, restriction_type, reason, admin_id, logged_at)
            VALUES
            (:restriction_id, :user_id, 'created', :type, :reason, :admin_id, NOW())
        ");
        $stmt->execute([
            ':restriction_id' => $restriction_id,
            ':user_id'        => $user_id,
            ':type'           => $restriction_type,
            ':reason'         => $reason,
            ':admin_id'       => $created_by,
        ]);

        $pdo->commit();
        return $restriction_id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * 指定した制限を解除し、履歴を記録する。
 * 対象の制限だけを解除し、他の制限には影響しない。
 */
function revokeRestriction(PDO $pdo, int $restriction_id, int $revoked_by): bool
{
    if ($restriction_id <= 0 || $revoked_by <= 0) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT restriction_id, user_id, restriction_type, reason
            FROM account_restrictions
            WHERE restriction_id = :id AND status = 'active'
            LIMIT 1
        ");
        $stmt->bindValue(':id', $restriction_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE account_restrictions
            SET status = 'revoked', revoked_at = NOW(), revoked_by = :revoked_by
            WHERE restriction_id = :id
        ");
        $stmt->bindValue(':id', $restriction_id, PDO::PARAM_INT);
        $stmt->bindValue(':revoked_by', $revoked_by, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            INSERT INTO account_restriction_logs
            (restriction_id, user_id, action, restriction_type, reason, admin_id, logged_at)
            VALUES
            (:restriction_id, :user_id, 'revoked', :type, :reason, :admin_id, NOW())
        ");
        $stmt->execute([
            ':restriction_id' => $restriction_id,
            ':user_id'        => (int) $row['user_id'],
            ':type'           => $row['restriction_type'],
            ':reason'         => $row['reason'],
            ':admin_id'       => $revoked_by,
        ]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * 制限IDから制限情報を取得する。
 */
function getRestrictionById(PDO $pdo, int $restriction_id): ?array
{
    if ($restriction_id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT
            restriction_id,
            user_id,
            restriction_type,
            reason,
            starts_at,
            ends_at,
            status,
            created_by,
            revoked_at,
            revoked_by,
            created_at
        FROM account_restrictions
        WHERE restriction_id = :id
        LIMIT 1
    ");
    $stmt->bindValue(':id', $restriction_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/**
 * ユーザーの制限・解除履歴を取得する。
 */
function getRestrictionHistory(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare("
        SELECT
            log_id,
            restriction_id,
            action,
            restriction_type,
            reason,
            admin_id,
            logged_at
        FROM account_restriction_logs
        WHERE user_id = :user_id
        ORDER BY logged_at DESC, log_id DESC
    ");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 制限がかかっているユーザーを検索する（管理者用）。
 */
function searchRestrictedUsers(PDO $pdo, string $keyword = ''): array
{
    $sql = "
        SELECT
            u.user_id,
            u.user_name,
            u.role,
            u.created_at,
            GROUP_CONCAT(
                DISTINCT CONCAT(r.restriction_type, ':', r.status)
                ORDER BY r.restriction_type SEPARATOR ','
            ) AS restriction_states
        FROM users AS u
        LEFT JOIN account_restrictions AS r
            ON r.user_id = u.user_id
            AND r.status = 'active'
            AND r.starts_at <= NOW()
            AND (r.ends_at IS NULL OR r.ends_at > NOW())
        WHERE 1 = 1";
    $params = [];
    if ($keyword !== '') {
        $sql .= ' AND u.user_name LIKE :keyword';
        $params[':keyword'] = '%' . $keyword . '%';
    }
    $sql .= ' GROUP BY u.user_id, u.user_name, u.role, u.created_at ORDER BY u.user_id DESC';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>