
<?php
// セッションを開始
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
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// データベース接続
$pdo = getPDO();

// CSRFトークン検証
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === '' ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。');
}

$user_id = (int) $_SESSION['user_id'];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 1. ユーザー自身のAIデータを削除
    $pdo->prepare('DELETE FROM ai_data WHERE user_id = ?')->execute([$user_id]);

    // 2. パスワードリセットトークンを削除
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user_id]);

    // 3. レコメンドデータを削除（ユーザー自身の）
    $pdo->prepare('DELETE FROM recommendations WHERE user_id = ?')->execute([$user_id]);

    // 4. フォロー関係を削除
    $pdo->prepare('DELETE FROM follows WHERE follower_id = ? OR following_id = ?')->execute([$user_id, $user_id]);

    // 5. ユーザー自身のお気に入りを削除
    $pdo->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$user_id]);

    // 6. カートを削除
    $pdo->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$user_id]);

    // 7. ユーザーが出品した商品に関連するデータを削除
    // 7a. その商品の画像を削除
    $pdo->prepare('DELETE FROM goods_images WHERE goods_id IN (SELECT goods_id FROM goods WHERE seller_id = ?)')->execute([$user_id]);

    // 7b. その商品の購入アイテムを削除
    $pdo->prepare('DELETE FROM buy_items WHERE goods_id IN (SELECT goods_id FROM goods WHERE seller_id = ?)')->execute([$user_id]);

    // 7c. 他ユーザーがその商品をお気に入りにしていたレコードを削除
    $pdo->prepare('DELETE FROM favorites WHERE goods_id IN (SELECT goods_id FROM goods WHERE seller_id = ?)')->execute([$user_id]);

    // 7d. その商品のレコメンドデータを削除
    $pdo->prepare('DELETE FROM recommendations WHERE goods_id IN (SELECT goods_id FROM goods WHERE seller_id = ?)')->execute([$user_id]);

    // 7e. 商品本体を削除
    $pdo->prepare('DELETE FROM goods WHERE seller_id = ?')->execute([$user_id]);

    // 8. ユーザーが購入した取引に関連するデータを削除
    // 8a. 購入アイテムを削除
    $pdo->prepare('DELETE FROM buy_items WHERE transaction_id IN (SELECT transaction_id FROM buy WHERE buyer_id = ?)')->execute([$user_id]);

    // 8b. その取引のレビューを削除
    $pdo->prepare('DELETE FROM reviews WHERE transaction_id IN (SELECT transaction_id FROM buy WHERE buyer_id = ?)')->execute([$user_id]);

    // 8c. その取引のカバーを削除
    $pdo->prepare('DELETE FROM cover WHERE transaction_id IN (SELECT transaction_id FROM buy WHERE buyer_id = ?)')->execute([$user_id]);

    // 8d. その取引のメッセージを削除
    $pdo->prepare('DELETE FROM messages WHERE transaction_id IN (SELECT transaction_id FROM buy WHERE buyer_id = ?)')->execute([$user_id]);

    // 8e. 取引本体を削除
    $pdo->prepare('DELETE FROM buy WHERE buyer_id = ?')->execute([$user_id]);

    // 9. ユーザーが書いたレビュー、受け取ったレビューを削除
    $pdo->prepare('DELETE FROM reviews WHERE reviewer_id = ? OR reviewee_id = ?')->execute([$user_id, $user_id]);

    // 10. ユーザーが送信・受信したメッセージを削除
    $pdo->prepare('DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?')->execute([$user_id, $user_id]);

    // 11. ユーザー本体を削除
    $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$user_id]);

    // コミット
    $pdo->commit();

    // セッションを破棄
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();

    // トップページへリダイレクト
    header('Location: ../index.php');
    exit;

} catch (PDOException $e) {
    // ロールバック
    $pdo->rollBack();
    error_log($e->getMessage());
    exit('アカウント削除中にエラーが発生しました。');
}
