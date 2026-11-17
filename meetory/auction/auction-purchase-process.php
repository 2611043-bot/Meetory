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

$address_input = $_POST['address'] ?? '';

if (!is_string($address_input)) {
    exit('配送先住所を正しく入力してください。');
}

$address = trim($address_input);

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

    // オークションと商品情報を取得してロック
    $stmt = $pdo->prepare(
        'SELECT
            g.goods_id,
            a.status,
            a.winner_user_id,
            a.winning_price
        FROM goods AS g
        INNER JOIN auctions AS a
            ON a.goods_id = g.goods_id
        WHERE g.goods_id = :goods_id
        FOR UPDATE'
    );

    $stmt->execute([
        'goods_id' => $goods_id
    ]);

    $auction = $stmt->fetch(PDO::FETCH_ASSOC);

    // オークション商品が存在するか確認
    if (!$auction) {
        throw new RuntimeException('オークション商品が見つかりません。');
    }

    // 落札者本人か確認
    if ((int) $auction['winner_user_id'] !== $buyer_id) {
        throw new RuntimeException('落札者本人のみ購入できます。');
    }

    // 売却済みか確認
    if ($auction['status'] !== 'sold') {
        throw new RuntimeException('このオークションは売却済みではありません。');
    }

    // 落札価格が1円以上か確認
    $winning_price = (int) $auction['winning_price'];

    if ($winning_price < 1) {
        throw new RuntimeException('落札価格が正しくありません。');
    }

    // 二重購入防止：buy_itemsに同じgoods_idが既に存在するか確認
    $duplicate_stmt = $pdo->prepare(
        'SELECT buy_item_id
        FROM buy_items
        WHERE goods_id = :goods_id
        LIMIT 1'
    );

    $duplicate_stmt->execute([
        'goods_id' => $goods_id
    ]);

    if ($duplicate_stmt->fetch(PDO::FETCH_ASSOC) !== false) {
        throw new RuntimeException('この商品は既に購入されています。');
    }

    // 購入取引を登録（落札価格を使用）
    $stmt = $pdo->prepare(
        'INSERT INTO buy
            (buyer_id, total_price, payment_method, transaction_status, address)
        VALUES
            (:buyer_id, :total_price, :payment_method, :transaction_status, :address)'
    );

    $stmt->execute([
        'buyer_id' => $buyer_id,
        'total_price' => $winning_price,
        'payment_method' => $payment_method,
        'transaction_status' => '購入済み',
        'address' => $address
    ]);

    // 登録された取引IDを取得
    $transaction_id = (int) $pdo->lastInsertId();

    // 購入商品を登録（落札価格を使用）
    $stmt = $pdo->prepare(
        'INSERT INTO buy_items
            (transaction_id, goods_id, purchase_price)
        VALUES
            (:transaction_id, :goods_id, :purchase_price)'
    );

    $stmt->execute([
        'transaction_id' => $transaction_id,
        'goods_id' => $goods_id,
        'purchase_price' => $winning_price
    ]);

    // すべて成功したら確定
    $pdo->commit();

    // CSRFトークンを更新
    unset($_SESSION['csrf_token']);

    // 購入完了画面へ移動
    header(
        'Location: ../purchase/purchase-complete.php?transaction_id=' .
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

    // バリデーションエラーなど
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
