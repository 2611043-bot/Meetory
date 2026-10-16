<?php
require_once '../../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// 商品一覧を取得
$sql = 'SELECT goods_id, goods_name, description, price, `condition`, listed_at
        FROM goods
        ORDER BY listed_at DESC';

$stmt = $pdo->query($sql);
$goods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../../meetory-header.php'; ?>

<main>
    <h1>商品一覧</h1>

    <?php if (count($goods_list) > 0): ?>

        <div class="goods-list">

            <?php foreach ($goods_list as $goods): ?>

                <article class="goods-item">

                    <h2>
                        <?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <p>
                        価格：
                        <?= number_format((int)$goods['price']) ?>円
                    </p>

                    <p>
                        商品の状態：
                        <?= htmlspecialchars($goods['condition'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <p>
                        <?= nl2br(htmlspecialchars($goods['description'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>

                    <a href="goods-detail.php?goods_id=<?= (int)$goods['goods_id'] ?>">
                        商品詳細を見る
                    </a>

                </article>

                <hr>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <p>現在、出品されている商品はありません。</p>

    <?php endif; ?>

</main>

<?php require '../../meetory-footer.php'; ?>
