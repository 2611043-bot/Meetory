<?php

// ログイン状態を確認するためSESSIONを開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRFトークンを生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ログインしていない場合
if (!isset($_SESSION['user_id'])) {

    // ログイン画面へ移動
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// DB接続
$pdo = getPDO();

// カテゴリーを取得
$sql = "SELECT category_id, category_name FROM categories ORDER BY category_id";
$stmt = $pdo->query($sql);

// 取得したカテゴリーを配列にする
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 入力エラーと保持値を取得
$errors = $_SESSION['goods_register_errors'] ?? [];
$old = $_SESSION['goods_register_old'] ?? [];

// エラーと保持値をクリア（次のリクエストのため）
unset($_SESSION['goods_register_errors']);
unset($_SESSION['goods_register_old']);

// 保持値のデフォルト
$old_goods_name = $old['goods_name'] ?? '';
$old_description = $old['description'] ?? '';
$old_price = $old['price'] ?? '';
$old_goods_condition = $old['goods_condition'] ?? '';
$old_category_id = $old['category_id'] ?? '';
$old_listing_type = $old['listing_type'] ?? 'normal';
$old_start_price = $old['start_price'] ?? '';
$old_buyout_price = $old['buyout_price'] ?? '';
$old_end_at = $old['end_at'] ?? '';
$old_auto_extend = $old['auto_extend'] ?? '1';

?>

<?php require_once '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/goods-register.css">

<h1>商品出品</h1>

<?php if (!empty($errors)): ?>
    <div class="register-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form action="goods-register-process.php" method="post" enctype="multipart/form-data" class="register-form">

    <!-- CSRF対策：トークンを送信 -->
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
    >

    <!-- 共通入力項目 -->
    <fieldset class="common-fields">
        <legend>商品情報</legend>

        <p>
            商品名
            <input type="text" name="goods_name" value="<?= htmlspecialchars($old_goods_name, ENT_QUOTES, 'UTF-8') ?>" required>
        </p>

        <p>
            商品説明
            <textarea name="description" required><?= htmlspecialchars($old_description, ENT_QUOTES, 'UTF-8') ?></textarea>
        </p>

        <p>
            商品状態
            <select name="goods_condition" required>
                <option value="">選択してください</option>
                <option value="新品" <?= $old_goods_condition === '新品' ? 'selected' : '' ?>>新品</option>
                <option value="未使用" <?= $old_goods_condition === '未使用' ? 'selected' : '' ?>>未使用</option>
                <option value="目立った傷や汚れなし" <?= $old_goods_condition === '目立った傷や汚れなし' ? 'selected' : '' ?>>目立った傷や汚れなし</option>
                <option value="やや傷や汚れあり" <?= $old_goods_condition === 'やや傷や汚れあり' ? 'selected' : '' ?>>やや傷や汚れあり</option>
                <option value="傷や汚れあり" <?= $old_goods_condition === '傷や汚れあり' ? 'selected' : '' ?>>傷や汚れあり</option>
            </select>
        </p>

        <p>
            カテゴリ
            <select name="category_id" required>
                <option value="">選択してください</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category['category_id'], ENT_QUOTES, 'UTF-8') ?>" <?= $old_category_id == $category['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="goods_image">商品画像</label>
            <input type="file" id="goods_image" name="goods_image" accept="image/*">
            <img id="preview">
        </p>

    </fieldset>

    <!-- 出品タイプ選択パネル -->
    <fieldset class="listing-type-panel">
        <legend>出品方法を選択</legend>

        <div class="listing-type-options">
            <label class="listing-type-option">
                <input type="radio" name="listing_type" value="normal" <?= $old_listing_type === 'normal' ? 'checked' : '' ?>>
                <span class="option-label">通常出品</span>
            </label>

            <label class="listing-type-option">
                <input type="radio" name="listing_type" value="auction" <?= $old_listing_type === 'auction' ? 'checked' : '' ?>>
                <span class="option-label">オークション出品</span>
            </label>
        </div>
    </fieldset>

    <!-- 通常出品入力欄 -->
    <fieldset class="listing-fields normal-fields" data-listing-type="normal">
        <legend>通常出品情報</legend>

        <p>
            販売価格
            <input type="number" name="price" min="1" value="<?= htmlspecialchars($old_price, ENT_QUOTES, 'UTF-8') ?>" required>
        </p>

    </fieldset>

    <!-- オークション出品入力欄 -->
    <fieldset class="listing-fields auction-fields" data-listing-type="auction">
        <legend>オークション出品情報</legend>

        <p>
            開始価格
            <input type="number" name="start_price" min="1" value="<?= htmlspecialchars($old_start_price, ENT_QUOTES, 'UTF-8') ?>" required>
        </p>

        <p>
            即決価格（任意）
            <input type="number" name="buyout_price" min="1" value="<?= htmlspecialchars($old_buyout_price, ENT_QUOTES, 'UTF-8') ?>">
        </p>

        <p>
            終了日時
            <input type="datetime-local" name="end_at" value="<?= htmlspecialchars($old_end_at, ENT_QUOTES, 'UTF-8') ?>" required>
        </p>

        <p>
            自動延長
            <select name="auto_extend" required>
                <option value="1" <?= $old_auto_extend === '1' ? 'selected' : '' ?>>ON</option>
                <option value="0" <?= $old_auto_extend === '0' ? 'selected' : '' ?>>OFF</option>
            </select>
        </p>

    </fieldset>

    <input type="submit" value="出品する">

</form>

<script src="../assets/js/goods-register.js"></script>
<script>
// 出品タイプ切り替え
(function() {
    const normalFields = document.querySelector('.normal-fields');
    const auctionFields = document.querySelector('.auction-fields');

    function toggleFields() {
        const checked = document.querySelector('input[name="listing_type"]:checked');
        if (!checked) return;

        if (checked.value === 'normal') {
            normalFields.style.display = '';
            auctionFields.style.display = 'none';
            // オークション必須項目を一時的に不要にする
            auctionFields.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
            normalFields.querySelector('input[name="price"]').setAttribute('required', '');
        } else {
            auctionFields.style.display = '';
            normalFields.style.display = 'none';
            // 通常必須項目を一時的に不要にする
            normalFields.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
            auctionFields.querySelectorAll('input[name="start_price"], input[name="end_at"], select[name="auto_extend"]').forEach(function(el) {
                el.setAttribute('required', '');
            });
        }
    }

    document.querySelectorAll('input[name="listing_type"]').forEach(function(radio) {
        radio.addEventListener('change', toggleFields);
    });

    // 初期表示
    toggleFields();
})();
</script>

<form action="../user/logout.php" method="post">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
    >

    <button type="submit">ログアウト</button>

</form>

<?php require '../../meetory-footer.php'; ?>
