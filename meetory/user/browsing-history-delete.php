
<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// POST以外のアクセスを拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// データベース接続
$pdo = getPDO();

// CSRFトークン確認
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === '' ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。閲覧履歴ページを開き直してください。');
}

// history_idを取得
$history_id = filter_input(INPUT_POST, 'history_id', FILTER_VALIDATE_INT);

if (
    $history_id === false ||
    $history_id === null ||
    $history_id <= 0
) {
    exit('履歴IDが正しくありません。');
}

// ユーザーID
$user_id = (int) $_SESSION['user_id'];

try {
    // ログイン中のユーザーIDとhistory_idを条件にDELETE（他人のデータは削除できない）
    $stmt = $pdo->prepare(
        'DELETE FROM browsing_history
        WHERE history_id = ?
            AND user_id = ?'
    );
    $stmt->execute([$history_id, $user_id]);

    // 閲覧履歴一覧へ戻す
    header('Location: browsing-history.php');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('閲覧履歴の削除に失敗しました。時間をおいて再度お試しください。');
}
