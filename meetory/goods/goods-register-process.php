<?php

// セッション開始
session_start();

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// DB接続
$pdo = getPDO();

// 出品フォームから送信された情報を受け取る
$goods_name = $_POST['goods_name'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? '';
$goods_condition = $_POST['goods_condition'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$confirm = $_POST['confirm'] ?? '';





 // ==============================
// 入力内容のチェック
// ==============================

$errors = [];

// 商品名・商品説明の型を確認
if (!is_string($goods_name) || !is_string($description)) {
    $errors[] = '商品名と商品説明を正しく入力してください。';
} else {
    // 前後の空白を削除
    $goods_name = trim($goods_name);
    $description = trim($description);

    // 商品名のチェック
    if ($goods_name === '') {
        $errors[] = '商品名を入力してください。';
    } elseif (mb_strlen($goods_name, 'UTF-8') < 2) {
        $errors[] = '商品名は2文字以上で入力してください。';
    } elseif (mb_strlen($goods_name, 'UTF-8') > 100) {
        $errors[] = '商品名は100文字以内で入力してください。';
    }

    // 商品説明のチェック
    if ($description === '') {
        $errors[] = '商品説明を入力してください。';
    }
}

// 価格のチェック
if (
    (!is_string($price) && !is_int($price))
    || !ctype_digit((string)$price)
    || (int)$price < 1
) {
    $errors[] = '価格は1円以上の整数で入力してください。';
}

// 商品状態のチェック
$allowed_conditions = [
    '新品',
    '未使用',
    '目立った傷や汚れなし',
    'やや傷や汚れあり',
    '傷や汚れあり'
];

if (
    !is_string($goods_condition)
    || !in_array($goods_condition, $allowed_conditions, true)
) {
    $errors[] = '正しい商品状態を選択してください。';
}

// カテゴリーのチェック
if (
    !is_string($category_id)
    || !ctype_digit($category_id)
    || (int)$category_id < 1
) {
    $errors[] = 'カテゴリーを選択してください。';
} else {
    // カテゴリーがDBに存在するか確認
    $sql = "SELECT COUNT(*) FROM categories WHERE category_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_id]);

    if ((int)$stmt->fetchColumn() === 0) {
        $errors[] = '正しいカテゴリーを選択してください。';
    }
}

// エラーがある場合は入力内容を保存してフォームに戻る
if (!empty($errors)) {
    $_SESSION['goods_register_errors'] = $errors;

    $_SESSION['goods_register_old'] = [
        'goods_name' => is_string($goods_name) ? $goods_name : '',
        'description' => is_string($description) ? $description : '',
        'price' => is_scalar($price) ? (string)$price : '',
        'goods_condition' => is_string($goods_condition) ? $goods_condition : '',
        'category_id' => is_scalar($category_id) ? (string)$category_id : ''
    ];

    header('Location: goods-register.php');
    exit;
}

// ==============================
// 画像のチェック
// ==============================

$image_uploaded = isset($_FILES['goods_image'])
    && $_FILES['goods_image']['error'] !== UPLOAD_ERR_NO_FILE;

/*
if ($image_uploaded) {

    // アップロードエラー
    if ($_FILES['goods_image']['error'] !== UPLOAD_ERR_OK) {
        exit('画像のアップロードに失敗しました。');
    }

    // ファイルサイズ
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($_FILES['goods_image']['size'] > $max_size) {
        exit('画像は5MB以下にしてください。');
    }

    // MIMEタイプを確認
    // $finfo = new finfo(FILEINFO_MIME_TYPE);
    // $mime_type = $finfo->file($_FILES['goods_image']['tmp_name']);

    $allowed_mime_types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    // if (!isset($allowed_mime_types[$mime_type])) {
    //     exit('JPG、PNG、GIF、WebPの画像を使用してください。');
    // }

    // 保存する拡張子
    // $extension = $allowed_mime_types[$mime_type];

    // ランダムなファイル名を作成
    $file_name = bin2hex(random_bytes(16)) . '.' . $extension;

    // 保存先
    $upload_dir = __DIR__ . '/../../uploads/temp/';

    // フォルダがなければ作成
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            exit('画像保存フォルダを作成できません。');
        }
    }

    // 保存先のフルパス
    $upload_path = $upload_dir . $file_name;

    // 画像を保存
    if (!move_uploaded_file(
        $_FILES['goods_image']['tmp_name'],
        $upload_path
    )) {
        exit('画像の保存に失敗しました。');
    }

    // DBに保存する画像パス
    $image_path = 'uploads/temp/' . $file_name;

    $_SESSION['goods_image'] = [
        'file_name' => $file_name,
        'image_path' => $image_path
    ];
}
*/

 // 確認画面からの2回目の送信なら、SESSIONの画像情報を使う
if ($confirm === '1' && isset($_SESSION['goods_image'])) {

    $file_name = $_SESSION['goods_image']['file_name'];
    $image_path = $_SESSION['goods_image']['image_path'];

    $upload_dir = __DIR__ . '/../../uploads/temp/';
    $upload_path = $upload_dir . $file_name;

    // 一時保存した画像が存在するか確認
    if (!is_file($upload_path)) {
        exit('一時保存した画像が見つかりません。もう一度出品してください。');
    }

    $image_uploaded = true;
}


// 確認画面を表示する
// confirm=1 が送られてきていない場合だけ表示
if ($confirm !== '1') {

    // 確認画面に表示するときのXSS対策
    $display_goods_name = htmlspecialchars($goods_name, ENT_QUOTES, 'UTF-8');
    $display_description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $display_price = htmlspecialchars($price, ENT_QUOTES, 'UTF-8');
    $display_goods_condition = htmlspecialchars($goods_condition, ENT_QUOTES, 'UTF-8');

    // カテゴリ名を取得
    $sql = "SELECT category_name FROM categories WHERE category_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_id]);

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    $display_category_name = htmlspecialchars(
        $category['category_name'],
        ENT_QUOTES,
        'UTF-8'
);

$display_image = '';

if (isset($_SESSION['goods_image'])) {

    $display_image_path = htmlspecialchars(
        $_SESSION['goods_image']['image_path'],
        ENT_QUOTES,
        'UTF-8'
    );

    $display_image = '<p>商品画像：</p>';
    $display_image .= '<img src="../../' . $display_image_path . '" width="200">';
}

$form_goods_name = htmlspecialchars($goods_name, ENT_QUOTES, 'UTF-8');
$form_description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
$form_price = htmlspecialchars($price, ENT_QUOTES, 'UTF-8');
$form_goods_condition = htmlspecialchars($goods_condition, ENT_QUOTES, 'UTF-8');
$form_category_id = htmlspecialchars((string)$category_id, ENT_QUOTES, 'UTF-8');

echo <<<HTML

<h2>商品情報の確認</h2>

<p>商品名：{$display_goods_name}</p>

<p>商品説明：{$display_description}</p>

<p>価格：{$display_price}円</p>

<p>商品状態：{$display_goods_condition}</p>

<p>カテゴリ：{$display_category_name}</p>

{$display_image}

<form action="goods-register-process.php" method="post">

    <input type="hidden" name="goods_name"
        value="{$form_goods_name}">

    <input type="hidden" name="description"
        value="{$form_description}">

    <input type="hidden" name="price"
        value="{$form_price}">

    <input type="hidden" name="goods_condition"
        value="{$form_goods_condition}">

    <input type="hidden" name="category_id"
        value="{$form_category_id}">

        <input type="hidden" name="confirm" value="1">

    <input type="submit" value="この内容で登録する">

</form>

<a href="goods-register.php">修正する</a>

HTML;

exit;
}

// ==============================
// DB登録
// ==============================

// 本保存先へ画像を移動したかどうか
$image_moved = false;

try {

    // トランザクション開始
    $pdo->beginTransaction();


    // 商品情報を登録するSQL
    $sql = <<<SQL
    INSERT INTO goods
    (seller_id, goods_name, description, price, `condition`, category_id, listed_at)
    VALUES
    (:seller_id, :goods_name, :description, :price, :goods_condition, :category_id, NOW())
    SQL;

    // SQLを準備
    $stmt = $pdo->prepare($sql);

    // SQLを実行
    $stmt->execute([
        ':seller_id' => $_SESSION['user_id'],
        ':goods_name' => $goods_name,
        ':description' => $description,
        ':price' => $price,
        ':goods_condition' => $goods_condition,
        ':category_id' => $category_id
    ]);

    // 登録した商品のgoods_idを取得
    $goods_id = $pdo->lastInsertId();


    // ==============================
    // 画像情報をgoods_imagesに登録
    // ==============================

/*
    // 画像を一時保存先から本保存先へ移動
    if ($image_uploaded) {

        // 本保存先のフォルダ
        $goods_dir = __DIR__ . '/../../uploads/goods/';

        // フォルダがなければ作成
        if (!is_dir($goods_dir)) {
            if (!mkdir($goods_dir, 0755, true) && !is_dir($goods_dir)) {
                throw new Exception('画像保存フォルダを作成できません。');
            }
        }

        // 移動先のフルパス
        $goods_path = $goods_dir . $file_name;

        // 一時保存先から本保存先へ移動
        if (!rename($upload_path, $goods_path)) {
            throw new Exception('画像の移動に失敗しました。');
        }

        // 画像の移動に成功した
        $image_moved = true;

        // DBに保存する画像パス
        $image_path = 'uploads/goods/' . $file_name;

        // 画像情報をDBに登録
        $sql = <<<SQL
        INSERT INTO goods_images
        (goods_id, image_path, display_order)
        VALUES
        (:goods_id, :image_path, :display_order)
        SQL;

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':goods_id' => $goods_id,
            ':image_path' => $image_path,
            ':display_order' => 1
        ]);
    }
*/

    // DBへの登録を確定
    $pdo->commit();


} catch (Exception $e) {

    // DB登録を取り消す
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 本保存先へ移動済みの画像があれば削除
    if (
        $image_moved
        && isset($goods_path)
        && is_file($goods_path)
    ) {
        unlink($goods_path);
    }

    // 一時保存先に画像が残っていれば削除
    if (
        isset($upload_path)
        && is_file($upload_path)
    ) {
        unlink($upload_path);
    }

    exit('商品登録に失敗しました。');
}

// SESSIONに保存した画像情報を削除
unset($_SESSION['goods_image']);

echo '商品情報を登録しました！';

echo <<< HTML

    <p>
        <a href="goods-list.php">商品一覧を見る</a>
    </p>

HTML;
?>