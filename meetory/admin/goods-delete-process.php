<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

// POST以外のアクセスを拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('不正なアクセスです。');
}

$pdo = getPDO();

// CSRFトークンを検証
$csrf_token = $_POST['csrf_token'] ?? '';
if (
    !is_string($csrf_token) ||
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    http_response_code(400);
    $_SESSION['goods_flash_message'] = '不正なリクエストです。画面を開き直してください。';
    $_SESSION['goods_flash_type'] = 'error';
    header('Location: goods-detail.php?goods_id=' . (int) ($_POST['goods_id'] ?? 0));
    exit;
}

// goods_idを整数として検証
$goods_id = filter_input(INPUT_POST, 'goods_id', FILTER_VALIDATE_INT);
if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    http_response_code(400);
    $_SESSION['goods_flash_message'] = '商品IDが正しくありません。';
    $_SESSION['goods_flash_type'] = 'error';
    header('Location: goods-list.php');
    exit;
}

$admin_id = (int) $_SESSION['user_id'];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 商品の対象行を排他ロックして取得
    $stmt = $pdo->prepare(
        'SELECT goods_id, seller_id, goods_name, is_sold, is_active
        FROM goods
        WHERE goods_id = ?
        FOR UPDATE'
    );
    $stmt->execute([$goods_id]);
    $goods = $stmt->fetch(PDO::FETCH_ASSOC);

    // 商品が存在することを確認
    if (!$goods) {
        $pdo->rollBack();
        http_response_code(404);
        $_SESSION['goods_flash_message'] = '商品が見つかりません。';
        $_SESSION['goods_flash_type'] = 'error';
        header('Location: goods-list.php');
        exit;
    }

    $goods_name = $goods['goods_name'];

    // 売却済み商品は削除を拒否
    if ((int) $goods['is_sold'] === 1) {
        $pdo->rollBack();
        $_SESSION['goods_flash_message'] = '売却済みの商品は削除できません。';
        $_SESSION['goods_flash_type'] = 'error';
        header('Location: goods-detail.php?goods_id=' . $goods_id);
        exit;
    }

    // 購入履歴がある商品は削除を拒否
    $count_stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM buy_items WHERE goods_id = ?'
    );
    $count_stmt->execute([$goods_id]);
    if ((int) $count_stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        $_SESSION['goods_flash_message'] = '購入履歴がある商品は削除できません。';
        $_SESSION['goods_flash_type'] = 'error';
        header('Location: goods-detail.php?goods_id=' . $goods_id);
        exit;
    }

    // オークション情報を確認
    $auction_stmt = $pdo->prepare(
        'SELECT auction_id, status, start_at, end_at
        FROM auctions
        WHERE goods_id = ?'
    );
    $auction_stmt->execute([$goods_id]);
    $auction = $auction_stmt->fetch(PDO::FETCH_ASSOC);

    if ($auction) {
        // 入札履歴があるオークションは削除を拒否
        $bid_count_stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM auction_bids WHERE auction_id = ?'
        );
        $bid_count_stmt->execute([$auction['auction_id']]);
        $bid_count = (int) $bid_count_stmt->fetchColumn();
        if ($bid_count > 0) {
            $pdo->rollBack();
            $_SESSION['goods_flash_message'] = '入札履歴があるオークション商品は削除できません。';
            $_SESSION['goods_flash_type'] = 'error';
            header('Location: goods-detail.php?goods_id=' . $goods_id);
            exit;
        }

        // 開催中のオークションは削除を拒否
        $is_holding = ($auction['status'] === 'active')
            && ($auction['start_at'] <= date('Y-m-d H:i:s'))
            && ($auction['end_at'] > date('Y-m-d H:i:s'));
        if ($is_holding) {
            $pdo->rollBack();
            $_SESSION['goods_flash_message'] = '開催中のオークション商品は削除できません。';
            $_SESSION['goods_flash_type'] = 'error';
            header('Location: goods-detail.php?goods_id=' . $goods_id);
            exit;
        }

        // 落札済みオークションは削除を拒否
        if ($auction['status'] === 'sold') {
            $pdo->rollBack();
            $_SESSION['goods_flash_message'] = '落札済みのオークション商品は削除できません。';
            $_SESSION['goods_flash_type'] = 'error';
            header('Location: goods-detail.php?goods_id=' . $goods_id);
            exit;
        }
    }

    // 削除前に画像パスを取得する
    $image_stmt = $pdo->prepare(
        'SELECT image_path
        FROM goods_images
        WHERE goods_id = ?'
    );
    $image_stmt->execute([$goods_id]);
    $image_paths = $image_stmt->fetchAll(PDO::FETCH_COLUMN);

    // 1. カート内の商品情報を削除
    $sql = 'DELETE FROM cart WHERE goods_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    // 2. 商品画像のDB情報を削除
    $sql = 'DELETE FROM goods_images WHERE goods_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    // 3. オークション情報がある場合は削除
    if ($auction) {
        $sql = 'DELETE FROM auctions WHERE goods_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goods_id]);
    }

    // 4. 商品本体を削除
    $sql = 'DELETE FROM goods WHERE goods_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);

    // 商品本体が1件削除されたか確認
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('商品を削除できませんでした。');
    }

    // コミット
    $pdo->commit();

    // 管理者操作ログを記録（削除が正常に完了した場合のみ）
    createAdminOperationLog(
        $admin_id,
        'goods_delete',
        'goods',
        $goods_id,
        '商品ID ' . $goods_id . ' 「' . $goods_name . '」を削除'
    );

    // フラッシュメッセージを設定
    $_SESSION['goods_flash_message'] = '商品を削除しました。';
    $_SESSION['goods_flash_type'] = 'success';

    header('Location: goods-list.php');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('goods-delete-process: ' . $e->getMessage());
    http_response_code(500);
    $_SESSION['goods_flash_message'] = '商品の削除に失敗しました。時間をおいて再度お試しください。';
    $_SESSION['goods_flash_type'] = 'error';
    header('Location: goods-detail.php?goods_id=' . $goods_id);
    exit;
}
