<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
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
if ($page === false || $page === null || $page < 1) {
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
];

foreach ($typeColumnMap as $typeKey => $colName) {
    if ((int) $settings[$colName] === 1) {
        $allowedTypeKeys[] = $typeKey;
    }
}

// 通知全体がOFF、または有効なタイプがない場合は空配列
if (!$notificationsEnabled || empty($allowedTypeKeys)) {
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
if (!$notificationsEnabled || empty($allowedTypeKeys)) {
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
            closeModal();
            targetForm.submit();
        }
    });
})();
</script>

<?php require '../../meetory-footer.php'; ?>
