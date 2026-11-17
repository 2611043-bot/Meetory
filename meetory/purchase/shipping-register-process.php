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

// CSRFトークン確認
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。画面を開き直してください。');
}

// 取引IDを取得
$transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);

if (
    $transaction_id === false ||
    $transaction_id === null ||
    $transaction_id <= 0
) {
    exit('取引IDが正しくありません。');
}

// 配送業者
$carrier = $_POST['carrier'] ?? '';
if (!is_string($carrier)) {
    $carrier = '';
}
$carrier = trim($carrier);

// 選択可能な配送業者
$allowed_carriers = ['ヤマト運輸', '日本郵便', '佐川急便', 'その他'];

if (!in_array($carrier, $allowed_carriers, true)) {
    exit('配送業者を正しく選択してください。');
}

// その他の場合の配送業者名
$carrier_other = $_POST['carrier_other'] ?? '';
if (!is_string($carrier_other)) {
    $carrier_other = '';
}
$carrier_other = trim($carrier_other);

if ($carrier === 'その他' && $carrier_other === '') {
    exit('配送業者名を入力してください。');
}

if ($carrier !== 'その他') {
    $carrier_other = null;
}

// 追跡番号
$tracking_number = $_POST['tracking_number'] ?? '';
if (!is_string($tracking_number)) {
    $tracking_number = '';
}
$tracking_number = trim($tracking_number);

if ($tracking_number === '' || mb_strlen($tracking_number) > 100) {
    exit('追跡番号を正しく入力してください。');
}

// DB接続
require_once '../../meetory-functions.php';
require_once '../notifications/create-notification.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 取引情報をロックして取得
    $stmt = $pdo->prepare(
        'SELECT transaction_id, buyer_id, transaction_status, cancelled_at
        FROM buy
        WHERE transaction_id = :transaction_id
        FOR UPDATE'
    );
    $stmt->execute(['transaction_id' => $transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new RuntimeException('取引情報が見つかりません。');
    }

    // キャンセル済みは発送できない
    if (!empty($transaction['cancelled_at'])) {
        throw new RuntimeException('キャンセル済みの取引は発送できません。');
    }

    // 購入済みのみ発送可能
    if ($transaction['transaction_status'] !== '購入済み') {
        throw new RuntimeException('この取引は発送手続きができない状態です。');
    }

    // この取引に含まれる商品の出品者か確認
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
        FROM buy_items AS bi
        INNER JOIN goods AS g
            ON bi.goods_id = g.goods_id
        WHERE bi.transaction_id = :transaction_id
          AND g.seller_id = :seller_id'
    );
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'seller_id' => $user_id
    ]);
    $is_seller = (int) $stmt->fetchColumn() > 0;

    if (!$is_seller) {
        throw new RuntimeException('この取引の配送情報を登録する権限がありません。');
    }

    // すでにこの出品者分の配送情報が登録済みの場合は重複登録不可
    $stmt = $pdo->prepare(
        'SELECT delivery_id FROM deliveries
        WHERE transaction_id = :transaction_id AND seller_id = :seller_id'
    );
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'seller_id' => $user_id
    ]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('この取引の配送情報はすでに登録されています。');
    }

    // 配送情報を登録（発送済み、発送日時を自動保存）
    $stmt = $pdo->prepare(
        'INSERT INTO deliveries
            (transaction_id, seller_id, carrier, carrier_other, tracking_number, delivery_status, shipped_at)
        VALUES
            (:transaction_id, :seller_id, :carrier, :carrier_other, :tracking_number, :delivery_status, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'seller_id' => $user_id,
        'carrier' => $carrier,
        'carrier_other' => $carrier_other,
        'tracking_number' => $tracking_number,
        'delivery_status' => 'shipped'
    ]);

    // この取引に関わるすべての出品者が配送済みになった場合のみ、
    // 取引ステータスを発送済みに変更する（複数出品者取引への対応）
    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT g.seller_id) AS seller_count
        FROM buy_items AS bi
        INNER JOIN goods AS g
            ON bi.goods_id = g.goods_id
        WHERE bi.transaction_id = :transaction_id'
    );
    $stmt->execute(['transaction_id' => $transaction_id]);
    $totalSellers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['seller_count'];

    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT d.seller_id) AS shipped_count
        FROM deliveries AS d
        WHERE d.transaction_id = :transaction_id
          AND d.delivery_status = :shipped_status'
    );
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'shipped_status' => 'shipped'
    ]);
    $shippedSellers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['shipped_count'];

    if ($totalSellers > 0 && $shippedSellers >= $totalSellers) {
        $stmt = $pdo->prepare(
            'UPDATE buy
            SET transaction_status = :new_status
            WHERE transaction_id = :transaction_id
              AND transaction_status = :current_status
              AND cancelled_at IS NULL'
        );
        $stmt->execute([
            'new_status' => '発送済み',
            'transaction_id' => $transaction_id,
            'current_status' => '購入済み'
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('発送状況を更新できませんでした。');
        }
    }

    // 更新を確定
    $pdo->commit();

    // 購入者へ発送通知を作成（重複は作成しない）
    $buyerId = (int) $transaction['buyer_id'];

    $stmtNotify = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications
         WHERE user_id = ? AND type = ? AND related_id = ?'
    );
    $stmtNotify->execute([$buyerId, 'shipping', $transaction_id]);
    $alreadyNotified = $stmtNotify->fetchColumn() > 0;

    if (!$alreadyNotified) {
        $goodsNames = [];
        $stmtGoods = $pdo->prepare(
            'SELECT g.goods_name
            FROM buy_items AS bi
            INNER JOIN goods AS g
                ON bi.goods_id = g.goods_id
            WHERE bi.transaction_id = :transaction_id
            ORDER BY bi.buy_item_id ASC'
        );
        $stmtGoods->execute(['transaction_id' => $transaction_id]);
        foreach ($stmtGoods->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $goodsNames[] = $name;
        }

        if ($goodsNames !== []) {
            $goodsList = implode('、', $goodsNames);
            createNotification(
                $pdo,
                $buyerId,
                'shipping',
                '商品が発送されました',
                '購入した「' . $goodsList . '」が発送されました',
                $transaction_id
            );
        }
    }

    // 配送情報画面へ戻る
    $return_to = $_POST['return_to'] ?? '';
    if (!is_string($return_to)) {
        $return_to = '';
    }
    $return_to = trim($return_to);

    if ($return_to === 'transaction-detail') {
        header(
            'Location: transaction-detail.php?transaction_id=' .
            $transaction_id
        );
    } else {
        header(
            'Location: shipping-detail.php?transaction_id=' .
            $transaction_id .
            '&message=' . urlencode('発送手続きを完了しました。')
        );
    }
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit('データベースエラーが発生しました。');

} catch (RuntimeException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit(
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );

} catch (Throwable $e) {

    error_log($e->getMessage());

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit('予期しないエラーが発生しました。');
}
