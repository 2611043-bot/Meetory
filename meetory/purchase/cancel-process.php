<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// POST以外のアクセスを拒否（GETによるキャンセル禁止）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
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

// 共通処理・DB接続
require_once '../../meetory-functions.php';
require_once '../notifications/create-notification.php';

$pdo = getPDO();

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';
if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    http_response_code(400);
    exit('不正なリクエストです。画面を開き直してください。');
}

// 取引IDを受け取る
$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
if ($transaction_id === false || $transaction_id === null || $transaction_id <= 0) {
    http_response_code(400);
    exit('取引IDが正しくありません。');
}

// キャンセル理由（任意、既存DBのcancel_reasonに合わせて保存）
$cancel_reason = $_POST['cancel_reason'] ?? '';
if (!is_string($cancel_reason)) {
    $cancel_reason = '';
}
$cancel_reason = trim($cancel_reason);
if (mb_strlen($cancel_reason) > 255) {
    $cancel_reason = mb_substr($cancel_reason, 0, 255);
}
if ($cancel_reason === '') {
    $cancel_reason = null;
}

$user_id = (int) $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // 取引情報を取得してロック（同時操作・二重キャンセル防止）
    $stmt = $pdo->prepare(
        'SELECT transaction_id, buyer_id, transaction_status
         FROM buy
         WHERE transaction_id = :transaction_id
         FOR UPDATE'
    );
    $stmt->execute(['transaction_id' => $transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new RuntimeException('取引情報が見つかりません。');
    }

    // 対象取引に含まれる商品と出品者をロックして取得
    $stmt = $pdo->prepare(
        'SELECT
            bi.goods_id,
            g.seller_id,
            g.goods_name
         FROM buy_items AS bi
         INNER JOIN goods AS g ON bi.goods_id = g.goods_id
         WHERE bi.transaction_id = :transaction_id
         FOR UPDATE'
    );
    $stmt->execute(['transaction_id' => $transaction_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new RuntimeException('取引に含まれる商品が見つかりません。');
    }

    // 操作者が購入者本人、または取引内すべての商品の出品者本人か確認
    $is_buyer = (int) $transaction['buyer_id'] === $user_id;
    $is_seller_of_all = true;
    $seller_ids = [];
    foreach ($items as $item) {
        $sid = (int) $item['seller_id'];
        $seller_ids[$sid] = true;
        if ($sid !== $user_id) {
            $is_seller_of_all = false;
        }
    }
    $is_seller = $is_seller_of_all && count($seller_ids) === 1 && isset($seller_ids[$user_id]);

    if (!$is_buyer && !$is_seller) {
        throw new RuntimeException('この取引をキャンセルする権限がありません。');
    }

    // キャンセル可能な取引状態の確認（発送前・未完了・未キャンセル）
    if ($transaction['transaction_status'] !== '購入済み') {
        throw new RuntimeException('この取引はキャンセルできません。');
    }

    // 取引ステータスをキャンセル済みに変更、キャンセル日時・実行者・理由を記録
    $stmt = $pdo->prepare(
        'UPDATE buy
         SET transaction_status = :cancelled_status,
             cancelled_at = CURRENT_TIMESTAMP,
             cancelled_by = :cancelled_by,
             cancel_reason = :cancel_reason
         WHERE transaction_id = :transaction_id
           AND transaction_status = :current_status'
    );
    $stmt->execute([
        'cancelled_status' => 'キャンセル済み',
        'cancelled_by' => $user_id,
        'cancel_reason' => $cancel_reason,
        'transaction_id' => $transaction_id,
        'current_status' => '購入済み',
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('取引のキャンセルに失敗しました。');
    }

    // 対象取引に含まれる商品の販売状態を未販売に戻す
    $stmtGoods = $pdo->prepare(
        'UPDATE goods
         SET is_sold = 0
         WHERE goods_id = :goods_id'
    );
    foreach ($items as $item) {
        $stmtGoods->execute(['goods_id' => (int) $item['goods_id']]);
    }

    // 通知用データを確定（コミット後に送信）
    $buyer_id = (int) $transaction['buyer_id'];
    $goods_names = [];
    foreach ($items as $item) {
        $goods_names[] = $item['goods_name'];
    }
    $goods_list = implode('、', $goods_names);
    $recipients = [];
    if ($is_buyer) {
        foreach (array_keys($seller_ids) as $sid) {
            $sid = (int) $sid;
            if ($sid !== $user_id) {
                $recipients[] = $sid;
            }
        }
    } else {
        if ($buyer_id !== $user_id) {
            $recipients[] = $buyer_id;
        }
    }

    $pdo->commit();

    // 取引相手へキャンセル通知（既存通知方式に合わせ、重複は作成しない）
    $notify_title = '取引がキャンセルされました';
    $notify_message = '「' . $goods_list . '」の取引がキャンセルされました。';
    foreach ($recipients as $recipient_id) {
        try {
            $stmtNotify = $pdo->prepare(
                'SELECT COUNT(*) FROM notifications
                 WHERE user_id = ? AND type = ? AND related_id = ? AND title = ?'
            );
            $stmtNotify->execute([$recipient_id, 'admin', $transaction_id, $notify_title]);
            $alreadyNotified = $stmtNotify->fetchColumn() > 0;
            if (!$alreadyNotified) {
                createNotification($pdo, $recipient_id, 'admin', $notify_title, $notify_message, $transaction_id);
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    // CSRFトークンを更新
    unset($_SESSION['csrf_token']);

    header('Location: cancel-complete.php?transaction_id=' . $transaction_id);
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit('キャンセル処理に失敗しました。時間をおいて再度お試しください。');

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));

} catch (Throwable $e) {
    error_log($e->getMessage());
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit('キャンセル処理に失敗しました。時間をおいて再度お試しください。');
}
