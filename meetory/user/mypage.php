
<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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
        g.goods_id,
        g.goods_name,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
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
            'purchase_price' => $row['purchase_price'],
            'image_path' => $row['image_path']
        ];
    }
}

$purchase_count = count($purchase_history);

$listing_count_stmt = $pdo->prepare(
    'SELECT
        COALESCE(SUM(
            CASE
                WHEN g.is_sold = 0 AND (
                    a.auction_id IS NULL
                    OR (
                        a.status = :auction_status
                        AND a.start_at <= NOW()
                        AND a.end_at > NOW()
                    )
                )
                THEN 1 ELSE 0
            END
        ), 0)
            AS active_goods_count,
        COALESCE(SUM(CASE WHEN g.is_sold = 1 THEN 1 ELSE 0 END), 0)
            AS sold_goods_count
    FROM goods AS g
    LEFT JOIN auctions AS a
        ON a.goods_id = g.goods_id
    WHERE g.seller_id = :seller_id'
);
$listing_count_stmt->execute([
    'auction_status' => 'active',
    'seller_id' => $user_id
]);
$listing_counts = $listing_count_stmt->fetch(PDO::FETCH_ASSOC);

$in_progress_stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT bi.buy_item_id)
    FROM buy_items AS bi
    INNER JOIN buy AS b
        ON b.transaction_id = bi.transaction_id
    INNER JOIN goods AS g
        ON g.goods_id = bi.goods_id
    WHERE (b.buyer_id = :buyer_id OR g.seller_id = :seller_id)
        AND b.transaction_status IN (:purchased, :shipped)'
);
$in_progress_stmt->execute([
    'buyer_id' => $user_id,
    'seller_id' => $user_id,
    'purchased' => '購入済み',
    'shipped' => '発送済み'
]);
$in_progress_count = (int) $in_progress_stmt->fetchColumn();

// 共通ヘッダー
require '../../meetory-header.php';

?>

<!-- ページ固有のスタイルシートを読み込む（マイページ専用） -->
<link
    rel="stylesheet"
    href="/Meetory/meetory/assets/css/mypage.css?v=<?= filemtime(__DIR__ . '/../assets/css/mypage.css') ?>"
>

<div class="mypage-container">
    <header class="mypage-heading">
        <h1>マイページ</h1>
        <p>アカウントと取引をまとめて確認できます。</p>
    </header>

    <section class="mypage-panel mypage-user-panel">
        <div>
            <p class="mypage-eyebrow">ユーザー情報</p>
            <h2><?= htmlspecialchars($user['user_name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mypage-user-email">
                <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <div class="mypage-link-list">
            <a href="profile.php?user_id=<?= $user_id ?>">プロフィールを見る</a>
            <a href="password-reset.php">パスワード再設定</a>
        </div>
    </section>

    <section class="mypage-panel">
        <h2>取引管理</h2>
        <div class="mypage-stat-grid">
            <a class="mypage-stat-card" href="#purchase-history">
                <span>購入履歴</span>
                <strong><?= $purchase_count ?>件</strong>
            </a>
            <a class="mypage-stat-card" href="../goods/my-goods.php">
                <span>出品中の商品</span>
                <strong><?= (int) $listing_counts['active_goods_count'] ?>件</strong>
            </a>
            <a class="mypage-stat-card" href="../goods/my-goods.php">
                <span>売却済みの商品</span>
                <strong><?= (int) $listing_counts['sold_goods_count'] ?>件</strong>
            </a>
            <div class="mypage-stat-card">
                <span>取引中の商品</span>
                <strong><?= $in_progress_count ?>件</strong>
            </div>
        </div>
    </section>

    <section class="mypage-panel">
        <h2>出品管理</h2>
        <div class="mypage-action-grid">
            <a class="mypage-action-card mypage-action-primary" href="../goods/goods-register.php">
                <span aria-hidden="true">＋</span>
                商品を出品する
            </a>
            <a class="mypage-action-card" href="../goods/my-goods.php">
                通常出品の商品を確認する
            </a>
            <a
                class="mypage-action-card"
                href="profile.php?user_id=<?= $user_id ?>#profile-auctions"
            >
                自分の開催中オークションを確認する
            </a>
        </div>
    </section>

    <section class="mypage-panel">
        <h2>その他</h2>
        <nav class="mypage-link-list" aria-label="その他のメニュー">
            <a href="favorites.php">ほしいものリスト</a>
            <a href="../messages/message-center.php">メッセージ</a>
            <a href="logout.php">ログアウト</a>
        </nav>
    </section>

    <section class="mypage-panel" id="purchase-history">
        <h2>購入履歴</h2>

<?php if (empty($purchase_history)): ?>

    <p>購入履歴はまだありません。</p>

<?php else: ?>

    <?php foreach ($purchase_history as $transaction_id => $purchase): ?>

        <article class="mypage-purchase-card">
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
                        <!-- 商品画像 -->
                        <?php if (!empty($item['image_path'])): ?>
                            <div class="goods-image">
                                <img
                                    src="/Meetory/<?= htmlspecialchars(
                                        ltrim($item['image_path'], '/'),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $item['goods_name'] ?? '商品画像',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    width="200"
                                    loading="lazy"
                                >
                            </div>
                        <?php else: ?>
                            <p>画像はありません</p>
                        <?php endif; ?>

                        <!-- 商品名 -->
                        <p>
                            商品名：
                            <?= htmlspecialchars(
                                $item['goods_name'] ?? '商品情報なし',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>

                        <!-- 購入時の価格 -->
                        <p>
                            購入時の価格：
                            <?= number_format((int) $item['purchase_price']) ?>円
                        </p>
                    </li>

                <?php endforeach; ?>
                </ul>

            <?php endif; ?>

            <p>
                購入合計金額：
                <?= number_format((int) $purchase['total_price']) ?>円
            </p>

            <a href="../purchase/transaction-detail.php?transaction_id=<?= (int) $transaction_id ?>">
                取引詳細を見る
            </a>
        </article>

    <?php endforeach; ?>

<?php endif; ?>

    </section>
</div>

<?php require '../../meetory-footer.php'; ?>
