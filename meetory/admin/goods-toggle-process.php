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

// actionをstopまたはresumeに限定
$action = $_POST['action'] ?? '';
if (!in_array($action, ['stop', 'resume'], true)) {
    http_response_code(400);
    $_SESSION['goods_flash_message'] = '不正な操作です。';
    $_SESSION['goods_flash_type'] = 'error';
    header('Location: goods-detail.php?goods_id=' . $goods_id);
    exit;
}

$admin_id = (int) $_SESSION['user_id'];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 商品の対象行を排他ロックして取得
    $stmt = $pdo->prepare(
        'SELECT goods_id, is_sold, is_active
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

    // 売却済み商品は状態変更を拒否
    if ((int) $goods['is_sold'] === 1) {
        $pdo->rollBack();
        $_SESSION['goods_flash_message'] = '売却済みの商品は公開状態を変更できません。';
        $_SESSION['goods_flash_type'] = 'error';
        header('Location: goods-detail.php?goods_id=' . $goods_id);
        exit;
    }

    // 購入履歴がある商品は状態変更を拒否
    $count_stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM buy_items WHERE goods_id = ?'
    );
    $count_stmt->execute([$goods_id]);
    if ((int) $count_stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        $_SESSION['goods_flash_message'] = '購入履歴がある商品は公開状態を変更できません。';
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

    // 公開停止の場合、開催中のオークションは拒否
    if ($action === 'stop' && $auction) {
        $is_holding = ($auction['status'] === 'active')
            && ($auction['start_at'] <= date('Y-m-d H:i:s'))
            && ($auction['end_at'] > date('Y-m-d H:i:s'));
        if ($is_holding) {
            $pdo->rollBack();
            $_SESSION['goods_flash_message'] = '開催中のオークション商品は公開停止できません。';
            $_SESSION['goods_flash_type'] = 'error';
            header('Location: goods-detail.php?goods_id=' . $goods_id);
            exit;
        }
    }

    // 公開状態を更新
    $new_is_active = ($action === 'stop') ? 0 : 1;

    // すでに同じ状態の場合は重複操作として処理（エラーにはしない）
    if ((int) $goods['is_active'] === $new_is_active) {
        $pdo->commit();
        $message = ($action === 'stop')
            ? 'この商品はすでに公開停止されています。'
            : 'この商品はすでに公開中です。';
        $_SESSION['goods_flash_message'] = $message;
        $_SESSION['goods_flash_type'] = 'info';
        header('Location: goods-detail.php?goods_id=' . $goods_id);
        exit;
    }

    $update_stmt = $pdo->prepare(
        'UPDATE goods
        SET is_active = ?
        WHERE goods_id = ?'
    );
    $update_stmt->execute([$new_is_active, $goods_id]);

    if ($update_stmt->rowCount() !== 1) {
        $pdo->rollBack();
        $_SESSION['goods_flash_message'] = '公開状態の変更に失敗しました。';
        $_SESSION['goods_flash_type'] = 'error';
        header('Location: goods-detail.php?goods_id=' . $goods_id);
        exit;
    }

    // コミット
    $pdo->commit();

    // 管理者操作ログを記録（状態変更が正常に完了した場合のみ）
    $action_type = ($action === 'stop') ? 'goods_publish_stop' : 'goods_publish_resume';
    $details = '商品ID ' . $goods_id . ' の公開状態を' . ($action === 'stop' ? '非公開' : '公開') . 'に変更';
    createAdminOperationLog($admin_id, $action_type, 'goods', $goods_id, $details);

    // フラッシュメッセージを設定
    $message = ($action === 'stop')
        ? '商品を公開停止しました。'
        : '商品を再公開しました。';
    $_SESSION['goods_flash_message'] = $message;
    $_SESSION['goods_flash_type'] = 'success';

    header('Location: goods-detail.php?goods_id=' . $goods_id);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('goods-toggle-process: ' . $e->getMessage());
    http_response_code(500);
    $_SESSION['goods_flash_message'] = '公開状態の変更に失敗しました。時間をおいて再度お試しください。';
    $_SESSION['goods_flash_type'] = 'error';
    header('Location: goods-detail.php?goods_id=' . $goods_id);
    exit;
}
