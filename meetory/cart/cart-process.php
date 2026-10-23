<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// POST送信以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// CSRF対策
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。購入確認画面を開き直してください。');
}

// 配送先を取得
$address = trim($_POST['address'] ?? '');

// 支払い方法を取得
$payment_method = $_POST['payment_method'] ?? '';

// 入力チェック
if ($address === '' || mb_strlen($address) > 255) {
    exit('配送先住所を正しく入力してください。');
}

// 選択可能な支払い方法を制限する
$allowed_payment_methods = [
    '銀行振込（テスト）',
    'その他（テスト）'
];

if (!in_array($payment_method, $allowed_payment_methods, true)) {
    exit('支払い方法が正しくありません。');
}

try {

    // トランザクション開始
    $pdo->beginTransaction();

    // カート内の商品を取得し、購入直前の状態を確認する
    // FOR UPDATEで対象商品をロックする
    $sql = '
        SELECT
            c.cart_id,
            g.goods_id,
            g.goods_name,
            g.seller_id,
            g.price,
            g.is_sold
        FROM cart AS c
        INNER JOIN goods AS g
            ON c.goods_id = g.goods_id
        WHERE c.user_id = ?
        FOR UPDATE
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // カートが空の場合
    if (empty($cart_items)) {
        throw new RuntimeException(
            'カートに商品がありません。'
        );
    }

    $total_price = 0;

    // カート内の全商品をチェックする
    foreach ($cart_items as $item) {

        // 自分が出品した商品は購入できない
        if ((int) $item['seller_id'] === $user_id) {
            throw new RuntimeException(
                '自分が出品した商品がカートに含まれています。カートから削除してください。'
            );
        }

        // 売却済みの商品は購入できない
        if ((int) $item['is_sold'] !== 0) {
            throw new RuntimeException(
                '売却済みの商品がカートに含まれています。カートから削除してください。'
            );
        }

        // 合計金額を計算する
        $total_price += (int) $item['price'];
    }

    // 購入情報をbuyテーブルに登録する
    $sql = '
        INSERT INTO buy (
            buyer_id,
            total_price,
            purchased_at,
            payment_method,
            transaction_status,
            address
        )
        VALUES (?, ?, NOW(), ?, ?, ?)
    ';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $user_id,
        $total_price,
        $payment_method,
        '購入済み',
        $address
    ]);

    // 登録した購入情報のIDを取得する
    $transaction_id = (int) $pdo->lastInsertId();

    // 商品ごとの購入情報を登録する
    $insert_item_sql = '
        INSERT INTO buy_items (
            transaction_id,
            goods_id,
            purchase_price
        )
        VALUES (?, ?, ?)
    ';

    $insert_item_stmt = $pdo->prepare($insert_item_sql);

    // 商品を売却済みに変更するSQL
    $update_goods_sql = '
        UPDATE goods
        SET is_sold = 1
        WHERE goods_id = ?
            AND is_sold = 0
    ';

    $update_goods_stmt = $pdo->prepare($update_goods_sql);

    // 購入した商品をカートから削除するSQL
    $delete_cart_sql = '
        DELETE FROM cart
        WHERE cart_id = ?
            AND user_id = ?
    ';

    $delete_cart_stmt = $pdo->prepare($delete_cart_sql);

    foreach ($cart_items as $item) {

        $goods_id = (int) $item['goods_id'];
        $price = (int) $item['price'];
        $cart_id = (int) $item['cart_id'];

        // 購入商品を登録する
        $insert_item_stmt->execute([
            $transaction_id,
            $goods_id,
            $price
        ]);

        // 商品を売却済みにする
        $update_goods_stmt->execute([$goods_id]);

        // 1件更新されなければ処理を中止する
        if ($update_goods_stmt->rowCount() !== 1) {
            throw new RuntimeException(
                '商品の購入処理に失敗しました。'
            );
        }

        // カートから購入済み商品を削除する
        $delete_cart_stmt->execute([
            $cart_id,
            $user_id
        ]);
    }

    // すべて成功したら確定
    $pdo->commit();

    // 購入完了画面へ移動
    header(
        'Location: cart-complete.php?transaction_id='
        . $transaction_id
    );
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    exit(
        '購入処理中にエラーが発生しました。'
        . '<br><a href="cart-list.php">カートに戻る</a>'
    );

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
        . '<br><a href="cart-list.php">カートに戻る</a>'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    exit(
        '購入処理中にエラーが発生しました。'
        . '<br><a href="cart-list.php">カートに戻る</a>'
    );
}