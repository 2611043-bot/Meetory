
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

// DB接続
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

// 取引情報と購入者情報を取得
$sql = '
    SELECT
        b.transaction_id,
        b.buyer_id,
        b.total_price,
        b.purchased_at,
        b.transaction_status,
        b.completed_at,
        buyer.user_name AS buyer_name
    FROM buy AS b
    INNER JOIN users AS buyer
        ON b.buyer_id = buyer.user_id
    WHERE b.transaction_id = :transaction_id
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

// 取引に含まれる商品と出品者を取得
$sql = '
    SELECT
        g.goods_id,
        g.goods_name,
        g.seller_id,
        seller.user_name AS seller_name,
        bi.purchase_price
    FROM buy_items AS bi
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    INNER JOIN users AS seller
        ON g.seller_id = seller.user_id
    WHERE bi.transaction_id = :transaction_id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'transaction_id' => $transaction_id
]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ログイン中のユーザーが購入者または出品者か確認
$is_buyer = (int) $transaction['buyer_id'] === $user_id;
$is_seller = false;

foreach ($items as $item) {
    if ((int) $item['seller_id'] === $user_id) {
        $is_seller = true;
        break;
    }
}

if (!$is_buyer && !$is_seller) {
    exit('この取引を確認する権限がありません。');
}

// 共通ヘッダー
require_once '../../meetory-header.php';

?>

<h1>取引詳細</h1>

<p>
    取引ID：
    <?= (int) $transaction['transaction_id'] ?>
</p>

<p>
    取引状況：
    <?= htmlspecialchars(
        $transaction['transaction_status'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    購入日時：
    <?= htmlspecialchars(
        date('Y年m月d日 H:i:s', strtotime($transaction['purchased_at'])),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    購入者：
    <?= htmlspecialchars(
        $transaction['buyer_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<h2>商品情報</h2>

<?php if (empty($items)): ?>

    <p>商品情報が見つかりません。</p>

<?php else: ?>

    <?php foreach ($items as $item): ?>

        <section>
            <h3>
                <?= htmlspecialchars(
                    $item['goods_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h3>

            <p>
                出品者：
                <?= htmlspecialchars(
                    $item['seller_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                購入時の価格：
                <?= number_format((int) $item['purchase_price']) ?>円
            </p>
        </section>

        <hr>

    <?php endforeach; ?>

<?php endif; ?>

<p>
    合計金額：
    <?= number_format((int) $transaction['total_price']) ?>円
</p>

<?php if ($transaction['completed_at'] !== null): ?>

    <p>
        取引完了日時：
        <?= htmlspecialchars(
            date('Y年m月d日 H:i:s', strtotime($transaction['completed_at'])),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>


<?php if ($is_buyer): ?>

    <p>あなたはこの取引の購入者です。</p>

    <?php if ($transaction['transaction_status'] === '発送済み'): ?>

        <form
            action="complete-process.php"
            method="post"
            onsubmit="return confirm('商品を受け取ったことを確認し、取引を完了しますか？');"
        >
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

            <button type="submit">
                取引を完了する
            </button>
        </form>

    <?php elseif ($transaction['transaction_status'] === '取引完了'): ?>

        <p>この取引は完了しています。</p>

    <?php endif; ?>

<?php endif; ?>


<?php if ($is_seller): ?>

    <p>あなたはこの取引の出品者です。</p>

    <?php if ($transaction['transaction_status'] === '購入済み'): ?>

        <form
            action="shipping-process.php"
            method="post"
        >
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

            <button type="submit">
                発送済みにする
            </button>
        </form>

    <?php elseif ($transaction['transaction_status'] === '発送済み'): ?>

        <p>この商品は発送済みです。</p>

    <?php elseif ($transaction['transaction_status'] === '取引完了'): ?>

        <p>この取引は完了しています。</p>

    <?php endif; ?>

<?php endif; ?>

<p>
    <a href="../messages/message-inbox.php">メッセージ一覧へ</a>
</p>

<p>
    <a href="../user/mypage.php">マイページへ戻る</a>
</p>

<?php require_once '../../meetory-footer.php'; ?>
