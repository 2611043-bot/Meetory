<?php require_once '../../meetory-functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// POST送信確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// 商品ID確認
$goods_id = filter_input(INPUT_POST, 'goods_id', FILTER_VALIDATE_INT);

if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

$user_id = (int)$_SESSION['user_id'];

$pdo = getPDO();

try {
    // 削除処理をまとめて実行
    $pdo->beginTransaction();

    // 自分が出品した商品か確認
    $sql = 'SELECT goods_id
            FROM goods
            WHERE goods_id = ? AND seller_id = ?
            FOR UPDATE';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id, $user_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        exit('商品が見つからないか、削除する権限がありません。');
    }

    // 商品画像のデータを先に削除
    $sql = 'DELETE FROM goods_images WHERE goods_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    // 商品本体を削除
    $sql = 'DELETE FROM goods
            WHERE goods_id = ? AND seller_id = ?';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id, $user_id]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('商品を削除できませんでした。');
    }

    // すべて成功したら確定
    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 詳細なエラーはログに記録
    error_log($e->getMessage());

    exit('商品を削除できませんでした。データベースの設定を確認してください。');
}

// 商品一覧へ戻る
header('Location: goods-list.php');
exit;