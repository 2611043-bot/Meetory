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
require_once __DIR__ . '/../../meetory-functions.php';

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

// 共通ヘッダー
require_once __DIR__ . '/../../meetory-header.php';

?>

<!-- ページ固有のスタイルシートを読み込む（マイページ専用） -->
<link rel="stylesheet" href="/Meetory/meetory/assets/css/mypage.css">

<div class="mypage-container">
    <h1 class="mypage-title">マイページ</h1>

    <div class="user-info-card">
        <h2>ユーザー情報</h2>

        <p class="info-row">
            <span class="info-label">ユーザー名：</span>
            <span class="info-value"><?= htmlspecialchars($user['user_name'], ENT_QUOTES, 'UTF-8') ?></span>
        </p>

        <p class="info-row">
            <span class="info-label">メールアドレス：</span>
            <span class="info-value"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></span>
        </p>

        <p class="info-row">
            <span class="info-label">会員登録日：</span>
            <span class="info-value">
                <?= htmlspecialchars(
                    date('Y年m月d日', strtotime($user['created_at'])),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>
        </p>
    </div>

    <hr class="section-divider">

    <div class="purchase-history-card">
        <h2>購入履歴</h2>

        <?php if (empty($purchase_history)): ?>

            <p class="empty-message">購入履歴はまだありません。</p>

        <?php else: ?>

            <?php foreach ($purchase_history as $transaction_id => $purchase): ?>

                <section class="transaction-box">
                    <h3>
                        取引番号：
                        <?= htmlspecialchars((string) $transaction_id, ENT_QUOTES, 'UTF-8') ?>
                    </h3>

                    <p class="meta-row">
                        購入日時：
                        <?= htmlspecialchars(
                            date('Y年m月d日 H:i:s', strtotime($purchase['purchased_at'])),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <p class="meta-row">
                        取引状況：
                        <span class="status-badge"><?= htmlspecialchars($purchase['transaction_status'], ENT_QUOTES, 'UTF-8') ?></span>
                    </p>

                    <h4>購入した商品</h4>

                    <?php if (empty($purchase['items'])): ?>

                        <p class="empty-message">商品情報を確認できません。</p>

                    <?php else: ?>

                        <ul class="item-list">
                        <?php foreach ($purchase['items'] as $item): ?>

                            <li class="item-card">
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
                                    <p class="no-image-text">画像はありません</p>
                                <?php endif; ?>

                                <div class="item-details">
                                    <!-- 商品名 -->
                                    <p class="item-name">
                                        商品名：
                                        <?= htmlspecialchars(
                                            $item['goods_name'] ?? '商品情報なし',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <!-- 購入時の価格 -->
                                    <p class="item-price">
                                        購入時の価格：
                                        <span><?= number_format((int) $item['purchase_price']) ?>円</span>
                                    </p>
                                </div>
                            </li>

                        <?php endforeach; ?>
                        </ul>

                    <?php endif; ?>

                    <p class="total-price-row">
                        購入合計金額：
                        <span class="total-price-value"><?= number_format((int) $purchase['total_price']) ?>円</span>
                    </p>

                    <hr class="transaction-divider">
                </section>

            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <div class="logout-container">
        <a href="logout.php" class="btn-logout">ログアウト</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../meetory-footer.php'; ?>