<?php

// ログイン状態を確認するためSESSIONを開始
session_start();

// ログインしていない場合
if (!isset($_SESSION['user_id'])) {

    // ログイン画面へ移動
    header('Location: ../user/login.php');
    exit;
}

?>

<?php require '../meetory-header.php'; ?>

<h1>商品出品</h1>

<form action="goods-register-process.php" method="post" enctype="multipart/form-data">

    <p>
        商品名
        <input type="text" name="goods_name" required>
    </p>

    <p>
        商品説明
        <textarea name="description" required></textarea>
    </p>

    <p>
        価格
        <input type="number" name="price" min="1" required>
    </p>

    <p>
        商品状態
        <select name="goods_condition" required>
            <option value="">選択してください</option>
            <option value="新品">新品</option>
            <option value="未使用">未使用</option>
            <option value="目立った傷や汚れなし">目立った傷や汚れなし</option>
            <option value="やや傷や汚れあり">やや傷や汚れあり</option>
            <option value="傷や汚れあり">傷や汚れあり</option>
        </select>
    </p>

    <p>
        カテゴリ
        <select name="category_id" required>
            <option value="">選択してください</option>
            <option value="1">ファッション</option>
            <option value="2">家電</option>
            <option value="3">本・漫画</option>
            <option value="4">ゲーム</option>
            <option value="5">その他</option>
        </select>
    </p>

    <p>
    <lavel for="goods_image">商品画像</lavel>

    <input type="file" id="goods_image" name="goods_image" accept="image/*">
    
    <img id="preview" style="width: 200px; display: none;">

        <script>
            document.getElementById('goods_image').addEventListener('change', function() {
            const file = this.files[0];

            if (file) {
            const preview = document.getElementById('preview');

            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
                }
            });
        </script>
    </p>

    <input type="submit" value="出品する">

</form>

<?php require '../meetory-footer.php'; ?>