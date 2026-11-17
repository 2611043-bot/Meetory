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
    header('Location: ../user/login.php');
    exit;
}

// 共通処理・DB接続を読み込む
require_once '../../meetory-functions.php';
require_once '../notifications/create-notification.php';

$pdo = getPDO();

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === '' ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。画面を開き直してください。');
}

// フォームから値を取得
$goods_id = filter_input(INPUT_POST, 'goods_id', FILTER_VALIDATE_INT);

// 配送先情報
$shipping_name = $_POST['shipping_name'] ?? '';
if (!is_string($shipping_name)) {
    $shipping_name = '';
}
$shipping_name = trim($shipping_name);

$shipping_postal_code = $_POST['shipping_postal_code'] ?? '';
if (!is_string($shipping_postal_code)) {
    $shipping_postal_code = '';
}
$shipping_postal_code = trim($shipping_postal_code);

$shipping_prefecture = $_POST['shipping_prefecture'] ?? '';
if (!is_string($shipping_prefecture)) {
    $shipping_prefecture = '';
}
$shipping_prefecture = trim($shipping_prefecture);

$shipping_city = $_POST['shipping_city'] ?? '';
if (!is_string($shipping_city)) {
    $shipping_city = '';
}
$shipping_city = trim($shipping_city);

$shipping_address_line = $_POST['shipping_address_line'] ?? '';
if (!is_string($shipping_address_line)) {
    $shipping_address_line = '';
}
$shipping_address_line = trim($shipping_address_line);

$shipping_building = $_POST['shipping_building'] ?? '';
if (!is_string($shipping_building)) {
    $shipping_building = '';
}
$shipping_building = trim($shipping_building);

$shipping_phone = $_POST['shipping_phone'] ?? '';
if (!is_string($shipping_phone)) {
    $shipping_phone = '';
}
$shipping_phone = trim($shipping_phone);

$payment_method = $_POST['payment_method'] ?? '';

// 入力チェック
if (!$goods_id || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

if ($shipping_name === '' || mb_strlen($shipping_name) > 100) {
    exit('配送先氏名を正しく入力してください。');
}

if ($shipping_postal_code === '' || mb_strlen($shipping_postal_code) > 20) {
    exit('郵便番号を正しく入力してください。');
}

if ($shipping_prefecture === '' || mb_strlen($shipping_prefecture) > 50) {
    exit('都道府県を正しく入力してください。');
}

if ($shipping_city === '' || mb_strlen($shipping_city) > 100) {
    exit('市区町村を正しく入力してください。');
}

if ($shipping_address_line === '' || mb_strlen($shipping_address_line) > 255) {
    exit('町名・番地を正しく入力してください。');
}

if (mb_strlen($shipping_building) > 255) {
    exit('建物名・部屋番号は255文字以内で入力してください。');
}

if ($shipping_phone === '' || mb_strlen($shipping_phone) > 20) {
    exit('電話番号を正しく入力してください。');
}

// 選択可能な支払い方法
$allowed_payment_methods = [
    '銀行振込（テスト）',
    'その他（テスト）'
];

if (
    !is_string($payment_method) ||
    !in_array($payment_method, $allowed_payment_methods, true)
) {
    exit('支払い方法を選択してください。');
}

// 購入者ID
$buyer_id = (int) $_SESSION['user_id'];

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 商品情報を取得してロック
    $stmt = $pdo->prepare(
        'SELECT goods_id, seller_id, goods_name, price, is_sold
        FROM goods
        WHERE goods_id = :goods_id
        FOR UPDATE'
    );

    $stmt->execute([
        'goods_id' => $goods_id
    ]);

    $goods = $stmt->fetch(PDO::FETCH_ASSOC);

    // 商品が存在するか確認
    if (!$goods) {
        throw new RuntimeException('商品が見つかりません。');
    }

    // 自分の商品は購入できない
    if ((int) $goods['seller_id'] === $buyer_id) {
        throw new RuntimeException('自分が出品した商品は購入できません。');
    }

    // 売却済みの商品は購入できない
    if ((int) $goods['is_sold'] === 1) {
        throw new RuntimeException('この商品はすでに売却されています。');
    }

    // 商品価格
    $price = (int) $goods['price'];

    if ($price <= 0) {
        throw new RuntimeException('商品の価格が正しくありません。');
    }

    // 購入取引を登録
    $stmt = $pdo->prepare(
        'INSERT INTO buy
            (buyer_id, total_price, payment_method, transaction_status, address,
             shipping_name, shipping_postal_code, shipping_prefecture, shipping_city,
             shipping_address_line, shipping_building, shipping_phone)
        VALUES
            (:buyer_id, :total_price, :payment_method, :transaction_status, :address,
             :shipping_name, :shipping_postal_code, :shipping_prefecture, :shipping_city,
             :shipping_address_line, :shipping_building, :shipping_phone)'
    );

    $stmt->execute([
        'buyer_id' => $buyer_id,
        'total_price' => $price,
        'payment_method' => $payment_method,
        'transaction_status' => '購入済み',
        'address' => $shipping_address_line,
        'shipping_name' => $shipping_name,
        'shipping_postal_code' => $shipping_postal_code,
        'shipping_prefecture' => $shipping_prefecture,
        'shipping_city' => $shipping_city,
        'shipping_address_line' => $shipping_address_line,
        'shipping_building' => $shipping_building,
        'shipping_phone' => $shipping_phone
    ]);

    // 登録された取引IDを取得
    $transaction_id = (int) $pdo->lastInsertId();

    // 購入商品を登録
    $stmt = $pdo->prepare(
        'INSERT INTO buy_items
            (transaction_id, goods_id, purchase_price)
        VALUES
            (:transaction_id, :goods_id, :purchase_price)'
    );

    $stmt->execute([
        'transaction_id' => $transaction_id,
        'goods_id' => $goods_id,
        'purchase_price' => $price
    ]);

    // 商品を売却済みに変更
    $stmt = $pdo->prepare(
        'UPDATE goods
        SET is_sold = 1
        WHERE goods_id = :goods_id
            AND is_sold = 0'
    );

    $stmt->execute([
        'goods_id' => $goods_id
    ]);

    // 更新できなかった場合は購入を取り消す
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('商品の購入処理に失敗しました。');
    }

    // すべて成功したら確定
    $pdo->commit();

    // 出品者へ購入通知を作成（重複は作成しない）
    $stmtNotify = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications
         WHERE user_id = ? AND type = ? AND related_id = ?'
    );
    $stmtNotify->execute([(int) $goods['seller_id'], 'purchase', $transaction_id]);
    $alreadyNotified = $stmtNotify->fetchColumn() > 0;

    if (!$alreadyNotified) {
        createNotification(
            $pdo,
            (int) $goods['seller_id'],
            'purchase',
            '商品が購入されました',
            '出品した「' . $goods['goods_name'] . '」が購入されました',
            $transaction_id
        );
    }

    // CSRFトークンを更新
    unset($_SESSION['csrf_token']);

    // 購入完了画面へ移動
    header(
        'Location: purchase-complete.php?transaction_id=' .
        $transaction_id
    );
    exit;

} catch (PDOException $e) {

    // データベースの詳しいエラーはログに記録
    error_log($e->getMessage());

    // 購入処理を取り消す
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 画面には一般的なメッセージだけ表示
    exit('購入処理に失敗しました。時間をおいて再度お試しください。');

} catch (RuntimeException $e) {

    // 自分の商品や売却済み商品などのエラー
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

    // 予期しないエラー
    error_log($e->getMessage());

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit('購入処理に失敗しました。時間をおいて再度お試しください。');
}