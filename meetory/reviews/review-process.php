
<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// POST送信以外を拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../user/mypage.php');
    exit;
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
if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    exit('不正なリクエストです。ページを再読み込みしてください。');
}

// 取引IDを確認
$transaction_id = filter_input(
    INPUT_POST,
    'transaction_id',
    FILTER_VALIDATE_INT
);

if (
    $transaction_id === false ||
    $transaction_id === null ||
    $transaction_id <= 0
) {
    exit('取引IDが正しくありません。');
}

// 評価点を確認
$rating = filter_input(
    INPUT_POST,
    'rating',
    FILTER_VALIDATE_INT
);

if (
    $rating === false ||
    $rating === null ||
    $rating < 1 ||
    $rating > 5
) {
    exit('評価は1〜5点で選択してください。');
}

// コメントを取得
$comment = $_POST['comment'] ?? '';

if (!is_string($comment)) {
    exit('コメントの入力内容が正しくありません。');
}

$comment = trim($comment);

// コメントの長さを確認
if (mb_strlen($comment, 'UTF-8') > 1000) {
    exit('コメントは1000文字以内で入力してください。');
}

// データベース接続
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

try {
    // 取引情報をロックして確認
    $pdo->beginTransaction();

    $sql = '
        SELECT
            b.buyer_id,
            b.transaction_status,
            seller.user_id AS seller_id
        FROM buy AS b
        INNER JOIN buy_items AS bi
            ON b.transaction_id = bi.transaction_id
        INNER JOIN goods AS g
            ON bi.goods_id = g.goods_id
        INNER JOIN users AS seller
            ON g.seller_id = seller.user_id
        WHERE b.transaction_id = :transaction_id
        LIMIT 1
        FOR UPDATE
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'transaction_id' => $transaction_id
    ]);

    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    // 取引の存在確認
    if (!$transaction) {
        throw new RuntimeException('取引情報が見つかりません。');
    }

    // 購入者本人か確認
    if ((int) $transaction['buyer_id'] !== $user_id) {
        throw new RuntimeException('この取引を評価する権限がありません。');
    }

    // 取引完了済みか確認
    if ($transaction['transaction_status'] !== '取引完了') {
        throw new RuntimeException('取引完了後に評価できます。');
    }

    // 自分自身を評価できないようにする
    if ((int) $transaction['seller_id'] === $user_id) {
        throw new RuntimeException('自分自身は評価できません。');
    }

    // 二重投稿を確認
    $sql = '
        SELECT COUNT(*)
        FROM reviews
        WHERE transaction_id = :transaction_id
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'transaction_id' => $transaction_id
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('この取引はすでに評価済みです。');
    }

    // レビューを保存
    $sql = '
        INSERT INTO reviews (
            transaction_id,
            reviewer_id,
            reviewee_id,
            rating,
            comment
        ) VALUES (
            :transaction_id,
            :reviewer_id,
            :reviewee_id,
            :rating,
            :comment
        )
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'transaction_id' => $transaction_id,
        'reviewer_id' => $user_id,
        'reviewee_id' => (int) $transaction['seller_id'],
        'rating' => $rating,
        'comment' => $comment === '' ? null : $comment
    ]);

    // 保存確定
    $pdo->commit();

    // 取引詳細画面へ戻る
    header(
        'Location: ../purchase/transaction-detail.php?transaction_id='
        . $transaction_id
    );
    exit;

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

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // UNIQUE制約による二重投稿も考慮
    if ($e->getCode() === '23000') {
        exit('この取引はすでに評価済みか、登録内容に問題があります。');
    }

    exit('データベースエラーが発生しました。時間をおいて再度お試しください。');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit('予期しないエラーが発生しました。');
}