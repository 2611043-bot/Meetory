<?php
session_start();

// CSRFトークンを生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require '../../meetory-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['goods_id']) || !ctype_digit((string)$_GET['goods_id'])) {
    exit('商品IDが正しくありません。');
}

$pdo = getPDO();

$is_admin = isset($_SESSION['role'])
    && $_SESSION['role'] === 'admin';

// 商品情報を取得
$sql = 'SELECT goods_id, seller_id, goods_name, description, price, `condition`, category_id
        FROM goods
        WHERE goods_id = ?';

$stmt = $pdo->prepare($sql);
$stmt->execute([(int)$_GET['goods_id']]);

$goods = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$goods) {
    exit('商品が見つかりません。');
}

// 管理者でなく、出品者本人でもない場合は拒否
if (
    !$is_admin &&
    (string)$goods['seller_id'] !== (string)$_SESSION['user_id']
) {
    exit('この商品を編集する権限がありません。');
}

// カテゴリー一覧を取得
$sql = 'SELECT category_id, category_name FROM categories';
$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

require '../../meetory-header.php';
?>

<h2>商品編集</h2>

<form action="goods-edit-process.php" method="post">

    <input type="hidden" name="goods_id"
            value="<?= htmlspecialchars((string)$goods['goods_id'], ENT_QUOTES, 'UTF-8') ?>">
            
    <!-- CSRFトークンを送信 -->
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
    >

    <p>
        <label for="goods_name">商品名</label><br>
        <input type="text" id="goods_name" name="goods_name"
                value="<?= htmlspecialchars($goods['goods_name'], ENT_QUOTES, 'UTF-8') ?>"
                required maxlength="255">
    </p>

    <p>
        <label for="description">商品説明</label><br>
        <textarea id="description" name="description" required><?= htmlspecialchars($goods['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    </p>

    <p>
        <label for="price">価格</label><br>
        <input type="number" id="price" name="price"
                value="<?= htmlspecialchars((string)$goods['price'], ENT_QUOTES, 'UTF-8') ?>"
                min="0" required>
    </p>

    <p>
        <label for="condition">商品の状態</label><br>
        <select id="condition" name="condition" required>
            <?php
            $conditions = ['新品', '未使用', '目立った傷や汚れなし',
                            'やや傷や汚れあり', '傷や汚れあり'];

            foreach ($conditions as $condition) {
                $selected = ($goods['condition'] === $condition) ? 'selected' : '';
                echo '<option value="' .
                    htmlspecialchars($condition, ENT_QUOTES, 'UTF-8') . '" ' .
                    $selected . '>' .
                    htmlspecialchars($condition, ENT_QUOTES, 'UTF-8') .
                    '</option>';
            }
            ?>
        </select>
    </p>

    <p>
        <label for="category_id">カテゴリー</label><br>
        <select id="category_id" name="category_id" required>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['category_id'] ?>"
                    <?= ((int)$goods['category_id'] === (int)$category['category_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($category['category_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <button type="submit">変更を保存</button>
</form>

<p>
    <a href="goods-detail.php?goods_id=<?= (int)$goods['goods_id'] ?>">
        商品詳細に戻る
    </a>
</p>

<?php require '../../meetory-footer.php'; ?>