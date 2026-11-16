<?php

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
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

// データベースに接続
$pdo = getPDO();

// ログイン中のユーザーID
$user_id = (int) $_SESSION['user_id'];

// 商品IDをURLから取得
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

// 商品IDが正しくない場合
if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

// 商品情報と落札情報を取得
$sql = '
    SELECT
        g.goods_id,
        g.goods_name,
        a.status,
        a.winner_user_id,
        a.winning_price
    FROM goods AS g
    INNER JOIN auctions AS a
        ON a.goods_id = g.goods_id
    WHERE g.goods_id = :goods_id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':goods_id' => $goods_id,
]);

$auction = $stmt->fetch(PDO::FETCH_ASSOC);

// オークション商品が見つからない場合
if (!$auction) {
    exit('オークション商品が見つかりません。');
}

// 落札者がログイン中のユーザーか確認
$is_winner = (int) $auction['winner_user_id'] === $user_id;

// 表示条件：売却済み・落札者本人・落札価格1円以上
$can_purchase =
    $auction['status'] === 'sold'
    && $is_winner
    && (int) $auction['winning_price'] >= 1;

// 二重購入防止：buy_itemsに同じgoods_idが既に存在する場合は画面を表示しない
$duplicate_sql = '
    SELECT COUNT(*)
    FROM buy_items
    WHERE goods_id = :goods_id
';

$duplicate_stmt = $pdo->prepare($duplicate_sql);
$duplicate_stmt->execute([
    ':goods_id' => $goods_id,
]);

$has_purchased = (int) $duplicate_stmt->fetchColumn() > 0;

if (!$can_purchase || $has_purchased) {
    exit('この画面を表示する権限がありません。');
}

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
?>

<?php require '../../meetory-header.php'; ?>

<main>

    <h1>オークション購入確認</h1>

    <p>
        商品名：
        <?= htmlspecialchars($auction['goods_name'], ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        落札価格：
        <?= number_format((int) $auction['winning_price']) ?>円
    </p>

    <form action="auction-purchase-process.php" method="post">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>"
        >

        <input
            type="hidden"
            name="goods_id"
            value="<?= (int) $auction['goods_id'] ?>"
        >

        <div class="form-group">
            <label for="address">配送先住所</label>
            <input
                type="text"
                id="address"
                name="address"
                maxlength="255"
                required
            >
        </div>

        <div class="form-group">
            <label for="payment_method">支払い方法</label>
            <select id="payment_method" name="payment_method" required>
                <option value="">選択してください</option>
                <option value="銀行振込（テスト）">銀行振込（テスト）</option>
                <option value="その他（テスト）">その他（テスト）</option>
            </select>
        </div>

        <p>※現在はテスト用のため、実際の決済や送金は行いません。</p>

        <button type="submit">購入を確定する</button>

    </form>

    <p>
        <a href="auction-detail.php?goods_id=<?= (int) $auction['goods_id'] ?>">
            オークション詳細に戻る
        </a>
    </p>

</main>

<?php require '../../meetory-footer.php'; ?>
