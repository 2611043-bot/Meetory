
<?php
require_once '../../meetory-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// POST送信確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrf_token) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。削除確認画面を開き直してください。');
}

// 商品ID確認
$goods_id = filter_input(INPUT_POST, 'goods_id', FILTER_VALIDATE_INT);

if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['role'])
    && $_SESSION['role'] === 'admin';

$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // 商品を取得してロック
    $sql = 'SELECT goods_id, seller_id
            FROM goods
            WHERE goods_id = ?
            FOR UPDATE';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    $goods = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$goods) {
        $pdo->rollBack();
        exit('商品が見つかりません。');
    }

    // 管理者でなく、出品者本人でもない場合は拒否
    if (
        !$is_admin &&
        (int)$goods['seller_id'] !== $user_id
    ) {
        $pdo->rollBack();
        exit('この商品を削除する権限がありません。');
    }

// 購入履歴がある商品は削除しない
$sql = 'SELECT COUNT(*)
        FROM buy_items
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

if ((int)$stmt->fetchColumn() > 0) {
    $pdo->rollBack();
    exit('購入履歴がある商品は削除できません。');
}

// 削除前に画像パスを取得する
$sql = 'SELECT image_path
        FROM goods_images
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

$image_paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ==================================
    // 1. カート内の商品情報を削除
    // ==================================
    $sql = 'DELETE FROM cart WHERE goods_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    // ==================================
    // 2. 商品画像のDB情報を削除
    // ==================================
    $sql = 'DELETE FROM goods_images WHERE goods_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    // ==================================
    // 3. 商品本体を削除
    // ==================================
    $sql = 'DELETE FROM goods WHERE goods_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    // 商品本体が1件削除されたか確認
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('商品を削除できませんでした。');
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 詳細なエラーはサーバーのログに記録する
    error_log($e->getMessage());

    // 画面には内部情報を表示しない
    exit('商品の削除に失敗しました。時間をおいて再度お試しください。');
}

// ==================================
// DB削除成功後に画像ファイルを削除
// ==================================

$goods_dir = realpath(__DIR__ . '/../../uploads/goods');

if ($goods_dir !== false) {
    foreach ($image_paths as $image_path) {

        // 保存先が uploads/goods/ のファイルだけを対象にする
        if (
            !is_string($image_path) ||
            !preg_match(
                '~\Auploads/goods/([a-zA-Z0-9_-]+\.(?:jpg|png|gif|webp))\z~i',
                $image_path,
                $matches
            )
        ) {
            error_log('商品画像のパスが不正です。');
            continue;
        }

        $file_path = $goods_dir . DIRECTORY_SEPARATOR . $matches[1];

        // 実際にファイルが存在するか確認
        if (is_file($file_path)) {
            $real_file = realpath($file_path);

            // 保存先フォルダの外にあるファイルは削除しない
            if (
                $real_file !== false &&
                dirname($real_file) === $goods_dir
            ) {
                if (!unlink($real_file)) {
                    error_log('商品画像ファイルを削除できませんでした。');
                }
            }
        }
    }
}

// 商品一覧へ戻る
header('Location: goods-list.php');
exit;