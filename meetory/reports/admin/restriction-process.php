<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: ../../user/login.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('このページを閲覧する権限がありません。');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrf_token) ||
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。画面を開き直してください。');
}

$action = $_POST['action'] ?? '';
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$admin_id = (int) $_SESSION['user_id'];

if ($user_id === false || $user_id === null || $user_id <= 0) {
    $_SESSION['restriction_flash'] = 'ユーザーIDが正しくありません。';
    header('Location: users.php');
    exit;
}

$pdo = getPDO();

// 対象ユーザーの存在確認
$stmt = $pdo->prepare('SELECT 1 FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$user_id]);
if (!$stmt->fetchColumn()) {
    $_SESSION['restriction_flash'] = 'ユーザーが見つかりません。';
    header('Location: users.php');
    exit;
}

$redirect = 'restriction-form.php?user_id=' . $user_id;

if ($action === 'create') {
    $restriction_type = $_POST['restriction_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $starts_at = $_POST['starts_at'] ?? '';
    $unlimited = isset($_POST['unlimited']) && $_POST['unlimited'] === '1';
    $ends_at = $unlimited ? null : ($_POST['ends_at'] !== '' ? $_POST['ends_at'] : null);

    if (!isValidRestrictionType($restriction_type)) {
        $_SESSION['restriction_flash'] = '不正な制限種別です。';
        header('Location: ' . $redirect);
        exit;
    }

    if ($reason === '') {
        $_SESSION['restriction_flash'] = '制限理由は必須です。';
        header('Location: ' . $redirect);
        exit;
    }

    // 開始日時のバリデーション
    if ($starts_at === '') {
        $starts_at = null;
    } else {
        $starts_at = validateDateTime($starts_at);
        if ($starts_at === null) {
            $_SESSION['restriction_flash'] = '開始日時が正しくありません。';
            header('Location: ' . $redirect);
            exit;
        }
    }

    // 終了日時のバリデーション
    if ($ends_at !== null) {
        $ends_at = validateDateTime($ends_at);
        if ($ends_at === null) {
            $_SESSION['restriction_flash'] = '終了日時が正しくありません。';
            header('Location: ' . $redirect);
            exit;
        }
        if ($starts_at !== null && $ends_at <= $starts_at) {
            $_SESSION['restriction_flash'] = '終了日時は開始日時より未来を指定してください。';
            header('Location: ' . $redirect);
            exit;
        }
    }

    try {
        createRestriction($pdo, $user_id, $restriction_type, $reason, $starts_at, $ends_at, $admin_id);
        $_SESSION['restriction_flash'] = '制限を設定しました。';
    } catch (Throwable $e) {
        error_log('restriction-process create: ' . $e->getMessage());
        $_SESSION['restriction_flash'] = '制限の設定に失敗しました。';
    }
    header('Location: ' . $redirect);
    exit;

} elseif ($action === 'revoke') {
    $restriction_id = filter_input(INPUT_POST, 'restriction_id', FILTER_VALIDATE_INT);
    if ($restriction_id === false || $restriction_id === null || $restriction_id <= 0) {
        $_SESSION['restriction_flash'] = '制限IDが正しくありません。';
        header('Location: ' . $redirect);
        exit;
    }

    // 対象ユーザーの制限であることを確認
    $restriction = getRestrictionById($pdo, $restriction_id);
    if ($restriction === null || (int) $restriction['user_id'] !== $user_id) {
        $_SESSION['restriction_flash'] = '制限が見つからないか、すでに解除されています。';
        header('Location: ' . $redirect);
        exit;
    }

    try {
        if (revokeRestriction($pdo, $restriction_id, $admin_id)) {
            $_SESSION['restriction_flash'] = '制限を解除しました。';
        } else {
            $_SESSION['restriction_flash'] = '制限の解除に失敗しました。';
        }
    } catch (Throwable $e) {
        error_log('restriction-process revoke: ' . $e->getMessage());
        $_SESSION['restriction_flash'] = '制限の解除に失敗しました。';
    }
    header('Location: ' . $redirect);
    exit;

} else {
    $_SESSION['restriction_flash'] = '不正な操作です。';
    header('Location: users.php');
    exit;
}

/**
 * datetime-local 入力を 'Y-m-d H:i:s' 形式に正規化し、不正なら null を返す。
 */
function validateDateTime(string $value): ?string
{
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if ($dt === false) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
}
