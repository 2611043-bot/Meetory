<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログイン確認
if (
    !isset($_SESSION['user_id']) or
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false or
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理
require_once '../../meetory-functions.php';

$pdo = getPDO();
$user_id = (int) $_SESSION['user_id'];

$settings = getNotificationSettings($pdo, $user_id);

// 全体がOFFなら空結果として扱う
$notificationsEnabled = (int) $settings['enabled'] === 1;

// ========================================
// CSRFトークン検証
// ========================================

$csrf_token = $_POST['csrf_token'] ?? '';
$csrf_valid = (
    isset($_SESSION['csrf_token']) &&
    is_string($_SESSION['csrf_token']) &&
    $_SESSION['csrf_token'] !== '' &&
    is_string($csrf_token) &&
    hash_equals($_SESSION['csrf_token'], $csrf_token)
);

// ========================================
// POSTアクション処理（既読・削除）
// ========================================

$flash_message = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $csrf_valid) {

    $action = $_POST['action'] ?? '';

    // ---- 個別既読 ----
    if ($action === 'mark_read') {
        $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
        if (
            $notification_id !== false &&
            $notification_id !== null &&
            $notification_id > 0
        ) {
            $stmt = $pdo->prepare(
                'UPDATE notifications
                 SET is_read = 1
                 WHERE notification_id = :notification_id
                   AND user_id = :user_id
                   AND is_read = 0'
            );
            $stmt->execute([
                ':notification_id' => $notification_id,
                ':user_id' => $user_id,
            ]);
            if ($stmt->rowCount() > 0) {
                $flash_message = '通知を既読にしました。';
                $flash_type = 'success';
            } else {
                $flash_message = '既読にできる通知が見つかりませんでした。';
                $flash_type = 'error';
            }
        } else {
            $flash_message = '通知IDが正しくありません。';
            $flash_type = 'error';
        }
    }

    // ---- すべて既読 ----
    elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = :user_id
               AND is_read = 0'
        );
        $stmt->execute([':user_id' => $user_id]);
        $count = (int) $stmt->rowCount();
        if ($count > 0) {
            $flash_message = $count . '件の通知を既読にしました。';
            $flash_type = 'success';
        } else {
            $flash_message = '未読の通知はありません。';
            $flash_type = 'info';
        }
    }

    // ---- 個別削除 ----
    elseif ($action === 'delete') {
        $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
        if (
            $notification_id !== false &&
            $notification_id !== null &&
            $notification_id > 0
        ) {
            $stmt = $pdo->prepare(
                'DELETE FROM notifications
                 WHERE notification_id = :notification_id
                   AND user_id = :user_id'
            );
            $stmt->execute([
                ':notification_id' => $notification_id,
                ':user_id' => $user_id,
            ]);
            if ($stmt->rowCount() > 0) {
                $flash_message = '通知を削除しました。';
                $flash_type = 'success';
            } else {
                $flash_message = '削除できる通知が見つかりませんでした。';
                $flash_type = 'error';
            }
        } else {
            $flash_message = '通知IDが正しくありません。';
            $flash_type = 'error';
        }
    }

    // ---- 既読通知一括削除 ----
    elseif ($action === 'delete_all_read') {
        $stmt = $pdo->prepare(
            'DELETE FROM notifications
             WHERE user_id = :user_id
               AND is_read = 1'
        );
        $stmt->execute([':user_id' => $user_id]);
        $count = (int) $stmt->rowCount();
        if ($count > 0) {
            $flash_message = $count . '件の既読通知を削除しました。';
            $flash_type = 'success';
        } else {
            $flash_message = '削除できる既読通知はありません。';
            $flash_type = 'info';
        }
    }

    else {
        $flash_message = '不正な操作です。';
        $flash_type = 'error';
    }

    // フラッシュメッセージをセッションに保存してリダイレクト（二重送信防止）
    $_SESSION['notify_flash_message'] = $flash_message;
    $_SESSION['notify_flash_type'] = $flash_type;

    $redirectUrl = 'index.php';
    if (!empty($_GET['type'])) {
        $redirectUrl .= '?type=' . urlencode($_GET['type']);
    }
    if (!empty($_GET['page'])) {
        $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'page=' . (int) $_GET['page'];
    }
    header('Location: ' . $redirectUrl);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_valid) {
    // CSRF無効時はフラッシュメッセージを設定してリダイレクト
    $_SESSION['notify_flash_message'] = '不正なリクエストです。ページを開き直してください。';
    $_SESSION['notify_flash_type'] = 'error';

    $redirectUrl = 'index.php';
    if (!empty($_GET['type'])) {
        $redirectUrl .= '?type=' . urlencode($_GET['type']);
    }
    if (!empty($_GET['page'])) {
        $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'page=' . (int) $_GET['page'];
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// ========================================
// フラッシュメッセージの取得とクリア
// ========================================

$flash_message = $_SESSION['notify_flash_message'] ?? '';
$flash_type = $_SESSION['notify_flash_type'] ?? '';
unset($_SESSION['notify_flash_message'], $_SESSION['notify_flash_type']);

// ========================================
// 通知種類フィルターの取得と検証
// ========================================

$allowedTypes = [
    'all' => null,
    'message' => 'message',
    'purchase' => 'purchase',
    'shipping' => 'shipping',
    'received' => 'received',
    'review' => 'review',
    'favorite' => 'favorite',
    'price_down' => 'price_down',
    'follow' => 'follow',
    'admin' => 'admin',
    'auction_bid_surpassed' => 'auction_bid_surpassed',
    'auction_won' => 'auction_won',
    'auction_ending' => 'auction_ending',
    'auction_bid_success' => 'auction_bid_success',
    'auction_ended_seller' => 'auction_ended_seller',
];

$typeLabels = [
    'all' => 'すべて',
    'message' => 'メッセージ',
    'purchase' => '購入',
    'shipping' => '発送',
    'received' => '受取',
    'review' => '評価',
    'favorite' => 'お気に入り',
    'price_down' => '値下げ',
    'follow' => 'フォロー',
    'admin' => '運営からのお知らせ',
    'auction_bid_surpassed' => '入札額を上回られた',
    'auction_won' => '落札しました',
    'auction_ending' => 'ウォッチ中の商品が終了',
    'auction_bid_success' => '入札を受け付けました',
    'auction_ended_seller' => 'オークションが終了しました',
];

$filter = $_GET['type'] ?? 'all';
if (!array_key_exists($filter, $allowedTypes)) {
    $filter = 'all';
}

$filterType = $allowedTypes[$filter]; // null なら全種類

// ========================================
// ページネーション
// ========================================

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if ($page === false or $page === null or $page < 1) {
    $page = 1;
}

$limit = 20;
$offset = ($page - 1) * $limit;

// ========================================
// 通知一覧を取得
// ========================================

// 許可されている通知タイプを設定から構築
$allowedTypeKeys = [];
$typeColumnMap = [
    'message'    => 'type_message',
    'purchase'   => 'type_purchase',
    'shipping'   => 'type_shipping',
    'received'   => 'type_received',
    'review'     => 'type_review',
    'favorite'   => 'type_favorite',
    'price_down' => 'type_price_down',
    'follow'     => 'type_follow',
    'admin'      => 'type_admin',
    'auction_bid_surpassed'   => 'type_auction_bid_surpassed',
    'auction_won'             => 'type_auction_won',
    'auction_ending'          => 'type_auction_ending',
    'auction_bid_success'     => 'type_auction_bid_success',
    'auction_ended_seller'    => 'type_auction_ended_seller',
];

foreach ($typeColumnMap as $typeKey => $colName) {
    if ((int) $settings[$colName] === 1) {
        $allowedTypeKeys[] = $typeKey;
    }
}

// 通知全体がOFF、または有効なタイプがない場合は空配列
if (!$notificationsEnabled or empty($allowedTypeKeys)) {
    $notifications = [];
    $totalCount = 0;
} else {
    $typePlaceholders = implode(',', array_map(fn($i) => ":type_$i", array_keys($allowedTypeKeys)));

    if ($filterType === null) {
        // 全種類（設定でONになっているもののみ）
        $sql = '
            SELECT
                notification_id,
                user_id,
                type,
                title,
                message,
                related_id,
                is_read,
                created_at
            FROM notifications
            WHERE user_id = :user_id
              AND type IN (' . $typePlaceholders . ')
            ORDER BY created_at DESC, notification_id DESC
            LIMIT :limit OFFSET :offset
        ';

        $countSql = '
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id
              AND type IN (' . $typePlaceholders . ')
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($allowedTypeKeys as $i => $typeKey) {
            $stmt->bindValue(":type_$i", $typeKey, PDO::PARAM_STR);
        }
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->prepare($countSql);
        $countStmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        foreach ($allowedTypeKeys as $i => $typeKey) {
            $countStmt->bindValue(":type_$i", $typeKey, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalCount = (int) $countStmt->fetchColumn();
    } else {
        // 指定種類のみ（かつ設定でONの場合のみ）
        if (!in_array($filterType, $allowedTypeKeys, true)) {
            // 設定でOFFのタイプが指定された場合は空結果
            $notifications = [];
            $totalCount = 0;
        } else {
            $sql = '
                SELECT
                    notification_id,
                    user_id,
                    type,
                    title,
                    message,
                    related_id,
                    is_read,
                    created_at
                FROM notifications
                WHERE user_id = :user_id
                  AND type = :type
                ORDER BY created_at DESC, notification_id DESC
                LIMIT :limit OFFSET :offset
            ';

            $countSql = '
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :user_id
                  AND type = :type
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':type', $filterType, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countStmt = $pdo->prepare($countSql);
            $countStmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $countStmt->bindValue(':type', $filterType, PDO::PARAM_STR);
            $countStmt->execute();
            $totalCount = (int) $countStmt->fetchColumn();
        }
    }
}

// 総ページ数
$totalPages = (int) ceil($totalCount / $limit);

// 未読件数（ヘッダー表示用など） - 設定でONのタイプのみカウント
if (!$notificationsEnabled or empty($allowedTypeKeys)) {
    $unreadCount = 0;
} else {
    $typePlaceholders = implode(',', array_map(fn($i) => ":type_$i", array_keys($allowedTypeKeys)));
    $unreadCountStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0 AND type IN (' . $typePlaceholders . ')'
    );
    $unreadCountStmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    foreach ($allowedTypeKeys as $i => $typeKey) {
        $unreadCountStmt->bindValue(":type_$i", $typeKey, PDO::PARAM_STR);
    }
    $unreadCountStmt->execute();
    $unreadCount = (int) $unreadCountStmt->fetchColumn();
}

// ========================================
// 共通ヘッダー
// ========================================

$body_class = 'notifications-page';

require '../../meetory-header.php';
?>

<link
    rel="stylesheet"
    href="/Meetory/meetory/assets/css/notifications.css?v=<?= filemtime(__DIR__ . '/../assets/css/notifications.css') ?>"
>
<style>
/* ページ全体の背景（Meetory標準の淡いブルーグラデーション） */
html:has(body.notifications-page),
body.notifications-page {
    background: linear-gradient(135deg, #e6f1fc 0%, #f4f8fc 52%, #ebf3fb 100%) !important;
    background-attachment: fixed !important;
}

body.notifications-page {
    padding-bottom: 110px !important;
}

/* フラッシュメッセージ */
body.notifications-page .notify-flash {
    max-width: 1080px;
    margin: 22px auto 0;
    padding: 13px 20px;
    border-radius: 16px;
    font-size: 13.5px;
    font-weight: 700;
    box-shadow: 0 6px 20px rgba(15, 41, 66, 0.06);
    box-sizing: border-box;
}

/* 上部フィルター＆操作バー（白のフローティングカード） */
body.notifications-page .notifications-filter {
    max-width: 1080px;
    margin: 24px auto 18px;
    padding: 20px 24px;
    border: 1px solid rgba(25, 118, 210, 0.12);
    border-radius: 20px;
    background-color: rgba(255, 255, 255, 0.96) !important;
    box-shadow: 0 10px 30px -8px rgba(15, 41, 66, 0.08);
    box-sizing: border-box;
}

body.notifications-page .filter-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

body.notifications-page .filter-link {
    display: inline-flex;
    align-items: center;
    height: 34px;
    padding: 0 14px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #f8fbff;
    color: #475569;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
}

body.notifications-page .filter-link:hover {
    background-color: #e8f3fe;
    border-color: #bfdbfe;
    color: #1976d2;
}

body.notifications-page .filter-link.filter-link-active {
    background-color: #1976d2 !important;
    border-color: #1976d2 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 10px rgba(25, 118, 210, 0.24);
}

/* 一括操作・通知設定ボタンバー */
body.notifications-page .filter-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #eef4fb;
}

body.notifications-page .filter-actions form {
    margin: 0;
}

body.notifications-page .bulk-btn,
body.notifications-page .settings-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 16px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s;
}

body.notifications-page .bulk-btn-read {
    border: 1px solid #bfdbfe;
    background-color: #eff6ff;
    color: #1976d2;
}

body.notifications-page .bulk-btn-read:hover {
    background-color: #1976d2;
    border-color: #1976d2;
    color: #ffffff;
}

body.notifications-page .bulk-btn-delete {
    border: 1px solid #fecdd3;
    background-color: #fff1f2;
    color: #e11d48;
}

body.notifications-page .bulk-btn-delete:hover {
    background-color: #e11d48;
    border-color: #e11d48;
    color: #ffffff;
}

body.notifications-page .settings-link {
    margin-left: auto;
    border: 1px solid #dce6f2;
    background-color: #f8fbff;
    color: #334155;
}

body.notifications-page .settings-link:hover {
    background-color: #e8f3fe;
    border-color: #bfdbfe;
    color: #1976d2;
}

/* 通知一覧エリア（白のフローティングカード） */
body.notifications-page .notifications-list {
    max-width: 1080px;
    margin: 0 auto 24px;
    padding: 8px 0;
    border: 1px solid rgba(25, 118, 210, 0.12);
    border-radius: 20px;
    background-color: rgba(255, 255, 255, 0.96) !important;
    box-shadow: 0 10px 30px -8px rgba(15, 41, 66, 0.08);
    overflow: hidden;
    box-sizing: border-box;
}

/* 空状態 */
body.notifications-page .notifications-empty {
    padding: 56px 20px;
    text-align: center;
    color: #64748b;
    font-size: 14px;
    font-weight: 600;
}

body.notifications-page .notifications-empty p {
    margin: 0;
}

/* 通知アイテム一覧 */
body.notifications-page .notification-items {
    margin: 0;
    padding: 0;
    list-style: none;
}

body.notifications-page .notification-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 22px;
    border-bottom: 1px solid #f1f5f9;
    background-color: #ffffff;
    transition: background-color 0.15s;
}

body.notifications-page .notification-item:last-child {
    border-bottom: none;
}

body.notifications-page .notification-item:hover {
    background-color: #f8fbff;
}

/* 未読通知のハイライト */
body.notifications-page .notification-item.notification-item-unread {
    background: linear-gradient(90deg, #eff6ff 0%, #f8fbff 100%) !important;
    border-left: 4px solid #1976d2;
    padding-left: 18px;
}

body.notifications-page .notification-item.notification-item-unread:hover {
    background: #e8f2fe !important;
}

body.notifications-page .notification-link {
    flex: 1;
    min-width: 0;
    text-decoration: none;
    color: inherit;
}

body.notifications-page .notification-content {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

/* 種類バッジ */
body.notifications-page .notification-type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    min-width: 68px;
    padding: 4px 10px;
    border-radius: 999px;
    background-color: #e3f2fd;
    color: #1565c0;
    font-size: 11.5px;
    font-weight: 800;
    text-align: center;
}

body.notifications-page .notification-text {
    flex: 1;
    min-width: 0;
}

body.notifications-page .notification-title {
    margin: 0 0 4px;
    font-size: 14.5px;
    font-weight: 800;
    color: #0f2942;
    line-height: 1.45;
}

body.notifications-page .notification-message {
    margin: 0;
    font-size: 13px;
    color: #475569;
    line-height: 1.55;
    overflow-wrap: anywhere;
}

body.notifications-page .notification-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
    flex-shrink: 0;
}

body.notifications-page .notification-date {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    white-space: nowrap;
}

body.notifications-page .notification-unread-mark {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
}

/* 個別「既読にする」「削除」ボタン */
body.notifications-page .notification-item-form {
    margin: 0;
    flex-shrink: 0;
}

body.notifications-page .action-btn {
    height: 32px;
    padding: 0 13px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
}

body.notifications-page .action-btn-read {
    border: 1px solid #bfdbfe;
    background-color: #eff6ff;
    color: #1976d2;
}

body.notifications-page .action-btn-read:hover {
    background-color: #1976d2;
    border-color: #1976d2;
    color: #ffffff;
}

body.notifications-page .action-btn-delete {
    border: 1px solid #fecdd3;
    background-color: #fff1f2;
    color: #e11d48;
}

body.notifications-page .action-btn-delete:hover {
    background-color: #e11d48;
    border-color: #e11d48;
    color: #ffffff;
}

/* ページネーション */
body.notifications-page .notifications-pagination {
    padding: 18px 20px;
    border-top: 1px solid #eef4fb;
    background-color: #f8fbff;
}

body.notifications-page .pagination-list {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}

body.notifications-page .pagination-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #ffffff;
    color: #1976d2;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s;
}

body.notifications-page .pagination-link:hover {
    background-color: #e8f3fe;
    border-color: #bfdbfe;
}

body.notifications-page .pagination-current {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

/* 確認モーダル */
body.notifications-page .delete-account-modal-content {
    border-radius: 20px !important;
    border: 1px solid rgba(25, 118, 210, 0.14);
    box-shadow: 0 20px 45px rgba(15, 41, 66, 0.2) !important;
}

body.notifications-page .delete-account-modal-btn {
    border-radius: 999px !important;
    font-weight: 700 !important;
}

/* スマホ対応 */
@media (max-width: 768px) {
    body.notifications-page .notifications-filter,
    body.notifications-page .notifications-list,
    body.notifications-page .notify-flash {
        margin-left: 12px;
        margin-right: 12px;
        border-radius: 16px;
    }

    body.notifications-page .notifications-filter {
        padding: 16px 14px;
    }

    body.notifications-page .settings-link {
        margin-left: 0;
    }

    body.notifications-page .notification-item {
        flex-wrap: wrap;
        padding: 14px 16px;
    }

    body.notifications-page .notification-item.notification-item-unread {
        padding-left: 12px;
    }

    body.notifications-page .notification-link {
        width: 100%;
        flex-basis: 100%;
    }

    body.notifications-page .notification-content {
        flex-wrap: wrap;
        gap: 8px;
    }

    body.notifications-page .notification-meta {
        flex-direction: row;
        align-items: center;
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<!-- フラッシュメッセージ -->
<?php if ($flash_message !== ''): ?>
    <div
        class="notify-flash notify-flash-<?= htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8') ?>"
        role="alert"
    >
        <?= htmlspecialchars($flash_message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- 通知種類フィルター＋一括操作 -->
<nav class="notifications-filter" aria-label="通知種類フィルター">
    <ul class="filter-list">
        <?php foreach ($allowedTypes as $filterKey => $filterValue): ?>
            <?php $isActive = ($filter === $filterKey); ?>
            <li>
                <a
                    href="index.php?type=<?= htmlspecialchars($filterKey, ENT_QUOTES, 'UTF-8') ?>&amp;page=<?= (int) $page ?>"
                    class="filter-link <?= $isActive ? 'filter-link-active' : '' ?>"
                >
                    <?= htmlspecialchars($typeLabels[$filterKey], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!empty($notifications)): ?>
        <div class="filter-actions">
            <?php if ($unreadCount > 0): ?>
                <form
                    action="index.php" method="post"
                    data-confirm
                    data-confirm-message="未読のすべての通知を既読にしますか？"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <button type="submit" class="bulk-btn bulk-btn-read">
                        すべて既読にする
                    </button>
                </form>
            <?php endif; ?>

            <?php
            // 既読通知があるか確認（現在のページではなくユーザー全体で） - 設定でONのタイプのみ
            $hasReadCount = 0;
            if ($notificationsEnabled && !empty($allowedTypeKeys)) {
                $typePlaceholders = implode(',', array_map(fn($i) => ":type_$i", array_keys($allowedTypeKeys)));
                $hasReadStmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 1 AND type IN (' . $typePlaceholders . ')'
                );
                $hasReadStmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                foreach ($allowedTypeKeys as $i => $typeKey) {
                    $hasReadStmt->bindValue(":type_$i", $typeKey, PDO::PARAM_STR);
                }
                $hasReadStmt->execute();
                $hasReadCount = (int) $hasReadStmt->fetchColumn();
            }
            ?>

            <?php if ($hasReadCount > 0): ?>
                <form
                    action="index.php" method="post"
                    data-confirm
                    data-confirm-message="既読のすべての通知を削除しますか？元に戻せません。"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete_all_read">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <button type="submit" class="bulk-btn bulk-btn-delete">
                        既読通知を一括削除
                    </button>
                </form>
            <?php endif; ?>

            <a href="../user/notification-settings.php" class="settings-link">
                <i class="fa-solid fa-gear"></i> 通知設定
            </a>
        </div>
    <?php endif; ?>
</nav>

<!-- 通知一覧 -->
<section class="notifications-list">

    <?php if (empty($notifications)): ?>

        <div class="notifications-empty">
            <?php if ($filterType !== null): ?>
                <p><?= htmlspecialchars($typeLabels[$filter], ENT_QUOTES, 'UTF-8') ?>の通知はありません。</p>
            <?php else: ?>
                <p>通知はありません。</p>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <ul class="notification-items">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $isRead = (int) $notification['is_read'] === 1;
                $typeLabel = $typeLabels[$notification['type']] ?? $notification['type'];
                $formattedDate = date('Y年m月d日 H:i', strtotime($notification['created_at']));
                $notificationId = (int) $notification['notification_id'];
                $clickUrl = htmlspecialchars(
                    'click.php?notification_id=' . $notificationId .
                    '&amp;type=' . urlencode($filter) .
                    '&amp;page=' . (int) $page,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
                <li class="notification-item <?= !$isRead ? 'notification-item-unread' : '' ?>" data-notification-id="<?= $notificationId ?>">

                    <!-- 通知本体をクリックで既読＋遷移 -->
                    <a
                        href="<?= $clickUrl ?>"
                        class="notification-link"
                        aria-label="<?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?> を開く"
                    >
                        <div class="notification-content">
                            <span class="notification-type-badge">
                                <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <div class="notification-text">
                                <h3 class="notification-title">
                                    <?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <p class="notification-message">
                                    <?= nl2br(htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8')) ?>
                                </p>
                            </div>
                            <div class="notification-meta">
                                <span class="notification-date">
                                    <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!$isRead): ?>
                                    <span class="notification-unread-mark">未読</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>

                    <!-- 個別既読フォーム -->
                    <?php if (!$isRead): ?>
                        <form
                            action="index.php" method="post"
                            class="notification-item-form"
                        >
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?= $notificationId ?>">
                            <input type="hidden" name="type" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="page" value="<?= (int) $page ?>">
                            <button
                                type="submit"
                                class="action-btn action-btn-read"
                                data-confirm
                                data-confirm-message="この通知を既読にしますか？"
                            >
                                既読にする
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- 個別削除フォーム -->
                    <form
                        action="index.php" method="post"
                        class="notification-item-form"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="notification_id" value="<?= $notificationId ?>">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="page" value="<?= (int) $page ?>">
                        <button
                            type="submit"
                            class="action-btn action-btn-delete"
                            data-confirm
                            data-confirm-message="この通知を削除しますか？元に戻せません。"
                        >
                            削除
                        </button>
                    </form>

                </li>
            <?php endforeach; ?>
        </ul>

        <!-- ページネーション -->
        <?php if ($totalPages > 1): ?>
            <nav class="notifications-pagination" aria-label="ページネーション">
                <ul class="pagination-list">
                    <?php if ($page > 1): ?>
                        <li>
                            <a
                                href="index.php?type=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>&amp;page=<?= (int) ($page - 1) ?>"
                                class="pagination-link"
                            >
                                <i class="fa-solid fa-chevron-left"></i> 前へ
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <span class="pagination-current">
                            ページ <?= (int) $page ?> / <?= (int) $totalPages ?>
                        </span>
                    </li>

                    <?php if ($page < $totalPages): ?>
                        <li>
                            <a
                                href="index.php?type=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>&amp;page=<?= (int) ($page + 1) ?>"
                                class="pagination-link"
                            >
                                次へ <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

</section>

<!-- 通知確認モーダル（アカウント削除確認と同じデザイン） -->
<div id="notificationConfirmOverlay" class="delete-account-modal-overlay"></div>
<div id="notificationConfirmModal" class="delete-account-modal">
    <div class="delete-account-modal-content">
        <h2 class="delete-account-modal-title">確認</h2>
        <p id="notificationConfirmMessage"></p>
        <div class="delete-account-modal-actions">
            <button
                type="button"
                class="delete-account-modal-btn delete-account-modal-cancel"
                id="notificationConfirmCancel"
            >
                いいえ
            </button>
            <button
                type="button"
                class="delete-account-modal-btn delete-account-modal-confirm"
                id="notificationConfirmConfirm"
            >
                はい
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('notificationConfirmOverlay');
    var modal = document.getElementById('notificationConfirmModal');
    var messageEl = document.getElementById('notificationConfirmMessage');
    var cancelBtn = document.getElementById('notificationConfirmCancel');
    var confirmBtn = document.getElementById('notificationConfirmConfirm');

    if (!overlay || !modal || !cancelBtn || !confirmBtn || !messageEl) {
        return;
    }

    var targetForm = null;

    function openModal(msg, form) {
        messageEl.textContent = msg;
        targetForm = form;
        overlay.classList.add('open');
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('open');
        modal.classList.remove('open');
        document.body.style.overflow = '';
        targetForm = null;
    }

    // data-confirm 付きフォームの submit をインターセプト
    var confirmForms = document.querySelectorAll('form[data-confirm]');
    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var msg = form.getAttribute('data-confirm-message');
            openModal(msg, form);
        });
    });

    // data-confirm 付きボタンのクリックをインターセプト
    var confirmButtons = document.querySelectorAll('button[data-confirm]');
    confirmButtons.forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var msg = btn.getAttribute('data-confirm-message');
            var form = btn.closest('form');
            openModal(msg, form);
        });
    });

    cancelBtn.addEventListener('click', function () {
        closeModal();
    });

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal();
        }
    });

    confirmBtn.addEventListener('click', function () {
        if (targetForm) {
            var form = targetForm;
            closeModal();
            form.submit();
        }
    });
})();
</script>

<?php require '../../meetory-footer.php'; ?>