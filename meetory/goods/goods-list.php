<?php
require_once '../../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// 検索条件を取得
$keyword = trim($_GET['keyword'] ?? '');
$min_price = trim($_GET['min_price'] ?? '');
$max_price = trim($_GET['max_price'] ?? '');
$condition = trim($_GET['condition'] ?? '');

// 商品一覧を取得
$sql = 'SELECT goods_id, goods_name, description, price, `condition`, listed_at
        FROM goods';

$conditions = [];
$params = [];

// 商品名で検索
if ($keyword !== '') {
    $conditions[] = 'goods_name LIKE :keyword';
    $params[':keyword'] = '%' . $keyword . '%';
}

// 最低価格で絞り込み
if ($min_price !== '' && ctype_digit($min_price)) {
    $conditions[] = 'price >= :min_price';
    $params[':min_price'] = (int)$min_price;
}

// 最高価格で絞り込み
if ($max_price !== '' && ctype_digit($max_price)) {
    $conditions[] = 'price <= :max_price';
    $params[':max_price'] = (int)$max_price;
}

// 商品状態で絞り込み
$allowed_conditions = [
    '新品',
    '未使用',
    '目立った傷や汚れなし',
    'やや傷や汚れあり',
    '傷や汚れあり'
];

if (in_array($condition, $allowed_conditions, true)) {
    $conditions[] = '`condition` = :condition';
    $params[':condition'] = $condition;
} else {
    $condition = '';
}

// 検索条件をSQLに追加
if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY listed_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$goods_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../../meetory-header.php'; ?>

<main>
    <h1>商品一覧</h1>

<!-- 商品検索フォーム -->
<form action="goods-list.php" method="get">

    <div>
        <label for="keyword">商品名：</label>
        <input
            type="text"
            id="keyword"
            name="keyword"
            value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="商品名を入力してください"
        >
    </div>

    <div>
        <label for="min_price">最低価格：</label>
        <input
            type="number"
            id="min_price"
            name="min_price"
            min="0"
            value="<?= htmlspecialchars($min_price, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="例：1000"
        >
        円
    </div>

    <div>
        <label for="max_price">最高価格：</label>
        <input
            type="number"
            id="max_price"
            name="max_price"
            min="0"
            value="<?= htmlspecialchars($max_price, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="例：10000"
        >
        円
    </div>

<div>
    <label for="condition">商品状態：</label>

    <select id="condition" name="condition">
        <option value="">すべて</option>

        <option value="新品"
            <?= $condition === '新品' ? 'selected' : '' ?>>
            新品
        </option>

        <option value="未使用"
            <?= $condition === '未使用' ? 'selected' : '' ?>>
            未使用
        </option>

        <option value="目立った傷や汚れなし"
            <?= $condition === '目立った傷や汚れなし' ? 'selected' : '' ?>>
            目立った傷や汚れなし
        </option>

        <option value="やや傷や汚れあり"
            <?= $condition === 'やや傷や汚れあり' ? 'selected' : '' ?>>
            やや傷や汚れあり
        </option>

        <option value="傷や汚れあり"
            <?= $condition === '傷や汚れあり' ? 'selected' : '' ?>>
            傷や汚れあり
        </option>
    </select>
</div>

    <button type="submit">検索</button>

    <a href="goods-list.php">検索をリセット</a>

</form>

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
