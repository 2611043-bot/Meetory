<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// ログイン中のユーザーID
$user_id = (int) $_SESSION['user_id'];

// CSRFトークンを作成
if (empty($_SESSION['csrf_token'])) {
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
        g.is_sold,
        u.user_name AS seller_name
    FROM cart AS c
    INNER JOIN goods AS g
        ON c.goods_id = g.goods_id
    INNER JOIN users AS u
        ON g.seller_id = u.user_id
    WHERE c.user_id = :user_id
    ORDER BY c.added_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $user_id
]);

$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 合計金額を計算
$total_price = 0;
$available_count = 0;

foreach ($cart_items as $item) {
    // 売却済み商品は合計に含めない
    if ((int) $item['is_sold'] === 0) {
        // 自分が出品した商品も購入対象にしない
        if ((int) $item['seller_id'] !== $user_id) {
            $total_price += (int) $item['price'];
            $available_count++;
        }
    }
}

?>

<?php require '../../meetory-header.php'; ?>

<main>

    <h1>ショッピングカート</h1>

    <?php if (empty($cart_items)): ?>

        <p>カートに商品はありません。</p>

        <p>
            <a href="../goods/goods-list.php">
                商品一覧を見る
            </a>
        </p>

    <?php else: ?>

        <?php foreach ($cart_items as $item): ?>

            <article>

                <h2>
                    <?= htmlspecialchars(
                        $item['goods_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h2>

                <p>
                    価格：
                    <?= number_format((int) $item['price']) ?>円
                </p>

                <p>
                    出品者：
                    <?= htmlspecialchars(
                        $item['seller_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <?php if ((int) $item['is_sold'] === 1): ?>

                    <p>この商品は売却済みです。購入できません。</p>

                <?php elseif ((int) $item['seller_id'] === $user_id): ?>

                    <p>自分が出品した商品は購入できません。</p>

                <?php else: ?>

                    <p>小計：<?= number_format((int) $item['price']) ?>円</p>

                <?php endif; ?>

                <p>
                    <a href="../goods/goods-detail.php?goods_id=<?= (int) $item['goods_id'] ?>">
                        商品詳細を見る
                    </a>
                </p>

                <form action="cart-delete.php" method="post">

                    <input
                        type="hidden"
                        name="cart_id"
                        value="<?= (int) $item['cart_id'] ?>"
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

                    <button type="submit">カートから削除</button>

                </form>

            </article>

            <hr>

        <?php endforeach; ?>

        <section>

            <h2>合計金額</h2>

            <p>
                <?= number_format($total_price) ?>円
            </p>

            <p>
                購入可能な商品数：<?= $available_count ?>点
            </p>

            <?php if ($available_count > 0): ?>

                <p>
                    ※ 購入手続き機能は、次の工程で実装します。
                </p>

            <?php else: ?>

                <p>購入できる商品がありません。</p>

            <?php endif; ?>

        </section>

    <p>
        <a href="cart-checkout.php">
            購入手続きへ進む
        </a>
    </p>

        <p>
            <a href="../goods/goods-list.php">
                商品一覧に戻る
            </a>
        </p>

    <?php endif; ?>

</main>

<?php require '../../meetory-footer.php'; ?>