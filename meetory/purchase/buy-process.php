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
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理・DB接続を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。画面を開き直してください。');
}

// フォームから値を取得
$goods_id = filter_input(INPUT_POST, 'goods_id', FILTER_VALIDATE_INT);
$address = trim($_POST['address'] ?? '');
$payment_method = $_POST['payment_method'] ?? '';

// 入力チェック
if (!$goods_id || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

if ($address === '' || mb_strlen($address) > 255) {
    exit('配送先住所を正しく入力してください。');
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
        'SELECT goods_id, seller_id, price, is_sold
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
            (buyer_id, total_price, payment_method, transaction_status, address)
        VALUES
            (:buyer_id, :total_price, :payment_method, :transaction_status, :address)'
    );

    $stmt->execute([
        'buyer_id' => $buyer_id,
        'total_price' => $price,
        'payment_method' => $payment_method,
        'transaction_status' => '購入済み',
        'address' => $address
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

    // CSRFトークンを更新
    unset($_SESSION['csrf_token']);

    // 購入完了画面へ移動
    header(
        'Location: purchase-complete.php?transaction_id=' .
        $transaction_id
    );
    exit;

} catch (Throwable $e) {

    // エラーが起きたら取り消す
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 想定したエラーはメッセージを表示
    if ($e instanceof RuntimeException) {
        exit(
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    // 詳細なエラーは画面に表示せず、ログに記録
    error_log($e->getMessage());

    exit('購入処理に失敗しました。時間をおいて再度お試しください。');
}