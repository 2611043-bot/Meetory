
<?php

// セッションを開始
session_start();

// ログインしているか確認
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// DB接続用の共通ファイルを読み込む
require_once '../../meetory-functions.php';

// ログイン中のユーザーIDを取得
$user_id = (int) $_SESSION['user_id'];

// DB接続
$pdo = getPDO();

// ユーザー情報を取得
$sql = '
    SELECT user_name, email, created_at
    FROM users
    WHERE user_id = ?
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ユーザーが見つからない場合
if (!$user) {
    session_unset();
    session_destroy();

    header('Location: login.php');
    exit;
}

// 購入履歴を取得
$sql = '
    SELECT
        b.transaction_id,
        b.total_price,
        b.purchased_at,
        b.transaction_status,
        bi.buy_item_id,
        bi.purchase_price,
        g.goods_name
    FROM buy AS b
    LEFT JOIN buy_items AS bi
        ON b.transaction_id = bi.transaction_id
    LEFT JOIN goods AS g
        ON bi.goods_id = g.goods_id
    WHERE b.buyer_id = ?
    ORDER BY
        b.purchased_at DESC,
        b.transaction_id DESC,
        bi.buy_item_id ASC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

$purchase_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 取引ごとに商品情報をまとめる
$purchase_history = [];

foreach ($purchase_rows as $row) {
    $transaction_id = $row['transaction_id'];

    if (!isset($purchase_history[$transaction_id])) {
        $purchase_history[$transaction_id] = [
            'total_price' => $row['total_price'],
            'purchased_at' => $row['purchased_at'],
            'transaction_status' => $row['transaction_status'],
            'items' => []
        ];
    }

    // 商品情報がある場合だけ追加
    if ($row['buy_item_id'] !== null) {
        $purchase_history[$transaction_id]['items'][] = [
            'goods_name' => $row['goods_name'],
            'purchase_price' => $row['purchase_price']
        ];
    }
}

// 共通ヘッダー
require '../../meetory-header.php';

?>

<h1>マイページ</h1>

<h2>ユーザー情報</h2>

<p>
    ユーザー名：
    <?= htmlspecialchars($user['user_name'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p>
    メールアドレス：
    <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p>
    会員登録日：
    <?= htmlspecialchars(
        date('Y年m月d日', strtotime($user['created_at'])),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<hr>

<h2>購入履歴</h2>

<?php if (empty($purchase_history)): ?>

    <p>購入履歴はまだありません。</p>

<?php else: ?>

    <?php foreach ($purchase_history as $transaction_id => $purchase): ?>

        <section>
            <h3>
                取引番号：
                <?= htmlspecialchars((string) $transaction_id, ENT_QUOTES, 'UTF-8') ?>
            </h3>

            <p>
                購入日時：
                <?= htmlspecialchars(
                    date('Y年m月d日H:i:s', strtotime($purchase['purchased_at'])),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                取引状況：
                <?= htmlspecialchars($purchase['transaction_status'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <h4>購入した商品</h4>

            <?php if (empty($purchase['items'])): ?>

                <p>商品情報を確認できません。</p>

            <?php else: ?>

                <ul>
                    <?php foreach ($purchase['items'] as $item): ?>

                        <li>
                            商品名：
                            <?= htmlspecialchars(
                                $item['goods_name'] ?? '商品情報なし',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            // 購入時の価格：
                            <?= number_format((int) $item['purchase_price']) ?>円
                        </li>

                    <?php endforeach; ?>
                </ul>

            <?php endif; ?>

            <p>
                購入合計金額：
                <?= number_format((int) $purchase['total_price']) ?>円
            </p>

            <hr>
        </section>

    <?php endforeach; ?>

<?php endif; ?>

<a href="logout.php">ログアウト</a>

<?php require '../../meetory-footer.php'; ?>
