<?php

// セッション開始
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

// 共通処理を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// カート内の商品を取得
$sql = '
    SELECT
        c.cart_id,
        g.goods_id,
        g.goods_name,
        g.price,
        g.seller_id,
        g.is_sold
    FROM cart AS c
    INNER JOIN goods AS g
        ON c.goods_id = g.goods_id
    WHERE c.user_id = :user_id
    ORDER BY c.added_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);

$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 購入可能な商品のみを抽出
$purchase_items = [];
$total_price = 0;

foreach ($cart_items as $item) {
    if (
        (int) $item['is_sold'] === 0 &&
        (int) $item['seller_id'] !== $user_id
    ) {
        $purchase_items[] = $item;
        $total_price += (int) $item['price'];
    }
}

?>

<?php require '../../meetory-header.php'; ?>

<main>

    <h1>購入内容の確認</h1>

    <?php if (empty($purchase_items)): ?>

        <p>購入できる商品がカートにありません。</p>

        <p>
            <a href="cart-list.php">カートに戻る</a>
        </p>

    <?php else: ?>

        <h2>購入する商品</h2>

        <?php foreach ($purchase_items as $item): ?>

            <article>

                <h3>
                    <?= htmlspecialchars(
                        $item['goods_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h3>

                <p>
                    価格：
                    <?= number_format((int) $item['price']) ?>円
                </p>

            </article>

            <hr>

        <?php endforeach; ?>

        <h2>合計金額</h2>

        <p>
            <?= number_format($total_price) ?>円
        </p>

        <form action="cart-process.php" method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <h2>配送先住所</h2>

            <label for="address">住所を入力してください</label><br>

            <input
                type="text"
                id="address"
                name="address"
                maxlength="255"
                required
            >

            <h2>支払い方法</h2>

            <label for="payment_method">
                支払い方法を選択してください
            </label><br>

            <select
                id="payment_method"
                name="payment_method"
                required
            >
                <option value="">選択してください</option>
                <option value="銀行振込（テスト）">
                    銀行振込（テスト）
                </option>
                <option value="その他（テスト）">
                    その他（テスト）
                </option>
            </select>

            <p>
                ※テスト用のため、実際の決済や送金は行いません。
            </p>

            <button type="submit">
                購入を確定する
            </button>

        </form>

        <p>
            <a href="cart-list.php">カートに戻る</a>
        </p>

    <?php endif; ?>

</main>

<?php require '../../meetory-footer.php'; ?>