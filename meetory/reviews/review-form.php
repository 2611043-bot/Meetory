
<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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

// データベース接続
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// URLから取引IDを取得
$transaction_id = filter_input(
    INPUT_GET,
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

// 取引情報と販売者情報を取得
$sql = '
    SELECT
        b.transaction_id,
        b.buyer_id,
        b.transaction_status,
        seller.user_id AS seller_id,
        seller.user_name AS seller_name
    FROM buy AS b
    INNER JOIN buy_items AS bi
        ON b.transaction_id = bi.transaction_id
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    INNER JOIN users AS seller
        ON g.seller_id = seller.user_id
    WHERE b.transaction_id = :transaction_id
    LIMIT 1
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'transaction_id' => $transaction_id
]);

$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

// 取引が存在しない場合
if (!$transaction) {
    exit('取引情報が見つかりません。');
}

// 購入者本人か確認
if ((int) $transaction['buyer_id'] !== $user_id) {
    exit('この取引を評価する権限がありません。');
}

// 取引完了済みか確認
if ($transaction['transaction_status'] !== '取引完了') {
    exit('取引完了後に評価できます。');
}

// 自分自身を評価できないようにする
if ((int) $transaction['seller_id'] === $user_id) {
    exit('自分自身は評価できません。');
}

// すでに評価済みか確認
$sql = '
    SELECT COUNT(*)
    FROM reviews
    WHERE transaction_id = :transaction_id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'transaction_id' => $transaction_id
]);

$review_exists = (int) $stmt->fetchColumn() > 0;

if ($review_exists) {
    exit('この取引はすでに評価済みです。');
}

// 共通ヘッダー
require_once '../../meetory-header.php';

?>

<h1>販売者を評価する</h1>

<p>
    取引ID：
    <?= (int) $transaction['transaction_id'] ?>
</p>

<p>
    販売者：
    <?= htmlspecialchars(
        $transaction['seller_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<form action="review-process.php" method="post">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            $csrf_token,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <input
        type="hidden"
        name="transaction_id"
        value="<?= (int) $transaction['transaction_id'] ?>"
    >

    <fieldset>
        <legend>評価（必須）</legend>

        <?php for ($rating = 5; $rating >= 1; $rating--): ?>

            <label>
                <input
                    type="radio"
                    name="rating"
                    value="<?= $rating ?>"
                    required
                >
                <?= str_repeat('★', $rating) ?>
                <?= $rating ?>点
            </label>

            <br>

        <?php endfor; ?>
    </fieldset>

    <p>
        <label for="comment">コメント（任意）</label>
    </p>

    <textarea
        id="comment"
        name="comment"
        rows="5"
        maxlength="1000"
        placeholder="取引の感想を入力してください"
    ></textarea>

    <p>
        <button type="submit">評価を投稿する</button>
    </p>
</form>

<p>
    <a href="../purchase/transaction-detail.php?transaction_id=<?= (int) $transaction['transaction_id'] ?>">
        取引詳細へ戻る
    </a>
</p>

<?php require_once '../../meetory-footer.php'; ?>