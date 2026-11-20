
<?php

// =============================================
// セッション・ログイン確認
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// POST送信確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('不正なアクセスです。');
}

// =============================================
// CSRF対策
// =============================================

$csrf_token = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrf_token) ||
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    exit('不正なリクエストです。フォームを開き直してください。');
}

// =============================================
// 共通処理・DB接続
// =============================================

require_once '../../meetory-functions.php';

$pdo = getPDO();

// =============================================
// 出品制限の確認
// =============================================

if (!canListGoods($pdo, (int) $_SESSION['user_id'])) {
    $suspension = isAccountSuspended($pdo, (int) $_SESSION['user_id']);

    if ($suspension !== null) {
        exit('アカウント停止中のため商品を出品できません。');
    }

    exit('出品制限が適用されているため商品を出品できません。');
}

// =============================================
// フォーム情報の取得
// =============================================

$goods_name = $_POST['goods_name'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? '';
$goods_condition = $_POST['goods_condition'] ?? '';
$category_id = $_POST['category_id'] ?? '';
$confirm = $_POST['confirm'] ?? '';
$listing_type = $_POST['listing_type'] ?? 'normal';
$listing_assist_mode = $_POST['listing_assist_mode'] ?? 'normal';

$start_price = $_POST['start_price'] ?? '';
$buyout_price = $_POST['buyout_price'] ?? '';
$end_at = $_POST['end_at'] ?? '';
$auto_extend = $_POST['auto_extend'] ?? '1';

// =============================================
// 入力内容のチェック
// =============================================

$errors = [];

// 商品名・商品説明の型を確認
if (!is_string($goods_name) || !is_string($description)) {
    $errors[] = '商品名と商品説明を正しく入力してください。';
} else {
    $goods_name = trim($goods_name);
    $description = trim($description);

    // 商品名
    if ($goods_name === '') {
        $errors[] = '商品名を入力してください。';
    } elseif (mb_strlen($goods_name, 'UTF-8') < 2) {
        $errors[] = '商品名は2文字以上で入力してください。';
    } elseif (mb_strlen($goods_name, 'UTF-8') > 100) {
        $errors[] = '商品名は100文字以内で入力してください。';
    }

    // 商品説明
    if ($description === '') {
        $errors[] = '商品説明を入力してください。';
    } elseif (mb_strlen($description, 'UTF-8') > 5000) {
        $errors[] = '商品説明は5000文字以内で入力してください。';
    }
}

// 商品状態
$allowed_conditions = [
    '新品',
    '未使用',
    '目立った傷や汚れなし',
    'やや傷や汚れあり',
    '傷や汚れあり'
];

if (
    !is_string($goods_condition) ||
    !in_array($goods_condition, $allowed_conditions, true)
) {
    $errors[] = '正しい商品状態を選択してください。';
}

// カテゴリー
if (
    !is_string($category_id) ||
    !ctype_digit($category_id) ||
    (int) $category_id < 1
) {
    $errors[] = 'カテゴリーを選択してください。';
} else {
    $sql = "SELECT COUNT(*) FROM categories WHERE category_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_id]);

    if ((int) $stmt->fetchColumn() === 0) {
        $errors[] = '正しいカテゴリーを選択してください。';
    }
}

// 出品タイプ
if (
    !is_string($listing_type) ||
    !in_array($listing_type, ['normal', 'auction'], true)
) {
    $errors[] = '正しい出品方法を選択してください。';
}

// AIアシストの選択状態
if (
    !is_string($listing_assist_mode) ||
    !in_array($listing_assist_mode, ['normal', 'ai'], true)
) {
    $listing_assist_mode = 'normal';
}

// 確認フラグ
if (!is_string($confirm) || !in_array($confirm, ['', '1'], true)) {
    $errors[] = '不正な送信内容です。';
}

// =============================================
// オークション・通常出品の価格チェック
// =============================================

if ($listing_type === 'auction') {

    // 開始価格
    if (
        (!is_string($start_price) && !is_int($start_price)) ||
        !ctype_digit((string) $start_price) ||
        (int) $start_price < 1
    ) {
        $errors[] = '開始価格は1円以上の整数で入力してください。';
    }

    // 即決価格
    if ($buyout_price !== '' && $buyout_price !== null) {
        if (
            (!is_string($buyout_price) && !is_int($buyout_price)) ||
            !ctype_digit((string) $buyout_price) ||
            (int) $buyout_price < 1
        ) {
            $errors[] = '即決価格は1円以上の整数で入力してください。';
        } elseif (
            ctype_digit((string) $start_price) &&
            (int) $start_price >= 1 &&
            (int) $buyout_price <= (int) $start_price
        ) {
            $errors[] = '即決価格は開始価格より高い金額を入力してください。';
        }
    }

    // 終了日時
    if (!is_string($end_at) || $end_at === '') {
        $errors[] = '終了日時を入力してください。';
    } else {
        $tz = new DateTimeZone('Asia/Tokyo');

        // datetime-local の形式を厳密に確認
        $input_end_at = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $end_at,
            $tz
        );

        $date_errors = DateTimeImmutable::getLastErrors();

        if (
            $input_end_at === false ||
            (
                $date_errors !== false &&
                (
                    $date_errors['warning_count'] > 0 ||
                    $date_errors['error_count'] > 0
                )
            ) ||
            (
                $input_end_at !== false &&
                $input_end_at->format('Y-m-d\TH:i') !== $end_at
            )
        ) {
            $errors[] = '正しい終了日時を入力してください。';
        } else {
            $now = new DateTimeImmutable('now', $tz);

            if ($input_end_at <= $now) {
                $errors[] = '終了日時は現在より未来の日時を入力してください。';
            }
        }
    }

    // 自動延長
    if (
        !is_string($auto_extend) ||
        !in_array($auto_extend, ['0', '1'], true)
    ) {
        $errors[] = '正しい自動延長設定を選択してください。';
    }

} elseif ($listing_type === 'normal') {

    // 通常出品の販売価格
    if (
        (!is_string($price) && !is_int($price)) ||
        !ctype_digit((string) $price) ||
        (int) $price < 1
    ) {
        $errors[] = '価格は1円以上の整数で入力してください。';
    }
}

// =============================================
// エラーがある場合はフォームに戻す
// =============================================

if (!empty($errors)) {

    $_SESSION['goods_register_errors'] = $errors;

    $_SESSION['goods_register_old'] = [
        'goods_name' => is_string($goods_name) ? $goods_name : '',
        'description' => is_string($description) ? $description : '',
        'price' => is_scalar($price) ? (string) $price : '',
        'goods_condition' => is_string($goods_condition) ? $goods_condition : '',
        'category_id' => is_scalar($category_id) ? (string) $category_id : '',
        'listing_type' => is_string($listing_type) ? $listing_type : 'normal',
        'listing_assist_mode' => $listing_assist_mode,
        'start_price' => is_scalar($start_price) ? (string) $start_price : '',
        'buyout_price' => is_scalar($buyout_price) ? (string) $buyout_price : '',
        'end_at' => is_string($end_at) ? $end_at : '',
        'auto_extend' => is_string($auto_extend) ? $auto_extend : '1'
    ];

    header('Location: goods-register.php');
    exit;
}

// =============================================
// 画像のチェック・一時保存
// =============================================

// 画像が送信されたか確認
$image_uploaded = isset($_FILES['goods_image'])
    && is_array($_FILES['goods_image'])
    && isset($_FILES['goods_image']['error'])
    && $_FILES['goods_image']['error'] !== UPLOAD_ERR_NO_FILE;

// 新しい出品フォームから送信された場合
// 前回の一時画像を削除する
if ($confirm !== '1') {

    if (
        isset($_SESSION['goods_image']['file_name']) &&
        is_string($_SESSION['goods_image']['file_name']) &&
        preg_match('/\A[a-f0-9]{32}\.(jpg|png|gif|webp)\z/i', $_SESSION['goods_image']['file_name'])
    ) {
        $old_temp_path = __DIR__ . '/../../uploads/temp/' .
            $_SESSION['goods_image']['file_name'];

        if (is_file($old_temp_path)) {
            unlink($old_temp_path);
        }
    }

    unset($_SESSION['goods_image']);
}

if ($image_uploaded) {

    // アップロードエラー
    if ($_FILES['goods_image']['error'] !== UPLOAD_ERR_OK) {
        exit('画像のアップロードに失敗しました。');
    }

    // ファイルサイズ
    $max_size = 5 * 1024 * 1024;
    $file_size = $_FILES['goods_image']['size'] ?? 0;

    if (
        !is_int($file_size) ||
        $file_size <= 0 ||
        $file_size > $max_size
    ) {
        exit('画像は1バイト以上、5MB以下にしてください。');
    }

    // 一時ファイル
    $tmp_name = $_FILES['goods_image']['tmp_name'] ?? '';

    if (
        !is_string($tmp_name) ||
        $tmp_name === '' ||
        !is_uploaded_file($tmp_name)
    ) {
        exit('正しい画像ファイルをアップロードしてください。');
    }

    // MIMEタイプ確認
    if (!class_exists('finfo')) {
        exit('画像の確認機能が利用できません。管理者に連絡してください。');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($tmp_name);

    if (!is_string($mime_type)) {
        exit('画像ファイルの種類を確認できませんでした。');
    }

    $allowed_mime_types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed_mime_types[$mime_type])) {
        exit('JPG、PNG、GIF、WebPの画像を使用してください。');
    }

    // 拡張子
    $extension = $allowed_mime_types[$mime_type];

    // ランダムなファイル名
    $file_name = bin2hex(random_bytes(16)) . '.' . $extension;

    // 一時保存先
    $upload_dir = __DIR__ . '/../../uploads/temp/';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            exit('画像保存フォルダを作成できません。');
        }
    }

    $upload_path = $upload_dir . $file_name;

    // 画像を保存
    if (!move_uploaded_file($tmp_name, $upload_path)) {
        exit('画像の保存に失敗しました。');
    }

    // セッションに画像情報を保存
    $image_path = 'uploads/temp/' . $file_name;

    $_SESSION['goods_image'] = [
        'file_name' => $file_name,
        'image_path' => $image_path
    ];
}

// =============================================
// 確認画面からの2回目の送信
// =============================================

if ($confirm === '1' && isset($_SESSION['goods_image'])) {

    $file_name = $_SESSION['goods_image']['file_name'] ?? '';

    if (
        !is_string($file_name) ||
        !preg_match('/\A[a-f0-9]{32}\.(jpg|png|gif|webp)\z/i', $file_name)
    ) {
        exit('画像情報が正しくありません。もう一度出品してください。');
    }

    $image_path = $_SESSION['goods_image']['image_path'] ?? '';

    if (!is_string($image_path) || $image_path !== 'uploads/temp/' . $file_name) {
        exit('画像情報が正しくありません。もう一度出品してください。');
    }

    $upload_dir = __DIR__ . '/../../uploads/temp/';
    $upload_path = $upload_dir . $file_name;

    if (!is_file($upload_path)) {
        exit('一時保存した画像が見つかりません。もう一度出品してください。');
    }

    $image_uploaded = true;
}

// =============================================
// 確認画面を表示
// =============================================

if ($confirm !== '1') {

    // XSS対策
    $display_goods_name = htmlspecialchars($goods_name, ENT_QUOTES, 'UTF-8');
    $display_description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $display_goods_condition = htmlspecialchars($goods_condition, ENT_QUOTES, 'UTF-8');

    // カテゴリー名
    $sql = "SELECT category_name FROM categories WHERE category_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_id]);

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($category === false) {
        exit('カテゴリーが見つかりません。');
    }

    $display_category_name = htmlspecialchars(
        $category['category_name'],
        ENT_QUOTES,
        'UTF-8'
    );

    // 出品タイプ
    $display_listing_type = $listing_type === 'auction'
        ? 'オークション出品'
        : '通常出品';

    // 価格
    if ($listing_type === 'auction') {

        $display_price_label = '開始価格';
        $display_price = htmlspecialchars((string) $start_price, ENT_QUOTES, 'UTF-8') . '円';

        $display_buyout_price = $buyout_price !== '' && $buyout_price !== null
            ? htmlspecialchars((string) $buyout_price, ENT_QUOTES, 'UTF-8') . '円'
            : 'なし';

        $display_end_at = htmlspecialchars($end_at, ENT_QUOTES, 'UTF-8');
        $display_auto_extend = $auto_extend === '1' ? 'ON' : 'OFF';

    } else {

        $display_price_label = '販売価格';
        $display_price = htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') . '円';
    }

    // 画像表示
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

    // 確認画面へ引き継ぐ値
    $form_goods_name = htmlspecialchars($goods_name, ENT_QUOTES, 'UTF-8');
    $form_description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $form_price = htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8');
    $form_goods_condition = htmlspecialchars($goods_condition, ENT_QUOTES, 'UTF-8');
    $form_category_id = htmlspecialchars((string) $category_id, ENT_QUOTES, 'UTF-8');
    $form_listing_type = htmlspecialchars($listing_type, ENT_QUOTES, 'UTF-8');
    $form_listing_assist_mode = htmlspecialchars($listing_assist_mode, ENT_QUOTES, 'UTF-8');
    $form_start_price = htmlspecialchars((string) $start_price, ENT_QUOTES, 'UTF-8');
    $form_buyout_price = htmlspecialchars((string) $buyout_price, ENT_QUOTES, 'UTF-8');
    $form_end_at = htmlspecialchars($end_at, ENT_QUOTES, 'UTF-8');
    $form_auto_extend = htmlspecialchars($auto_extend, ENT_QUOTES, 'UTF-8');

    $form_csrf_token = htmlspecialchars(
        $_SESSION['csrf_token'],
        ENT_QUOTES,
        'UTF-8'
    );

    echo <<<HTML

<h2>商品情報の確認</h2>

<p>商品名：{$display_goods_name}</p>

<p>商品説明：{$display_description}</p>

<p>出品方法：{$display_listing_type}</p>

<p>{$display_price_label}：{$display_price}</p>

HTML;

    if ($listing_type === 'auction') {
        echo <<<HTML

<p>即決価格：{$display_buyout_price}</p>

<p>終了日時：{$display_end_at}</p>

<p>自動延長：{$display_auto_extend}</p>

HTML;
    }

    echo <<<HTML

<p>商品状態：{$display_goods_condition}</p>

<p>カテゴリ：{$display_category_name}</p>

{$display_image}

<form action="goods-register-process.php" method="post">

    <input type="hidden" name="csrf_token"
        value="{$form_csrf_token}">

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

    <input type="hidden" name="listing_type"
        value="{$form_listing_type}">

    <input type="hidden" name="listing_assist_mode"
        value="{$form_listing_assist_mode}">

    <input type="hidden" name="start_price"
        value="{$form_start_price}">

    <input type="hidden" name="buyout_price"
        value="{$form_buyout_price}">

    <input type="hidden" name="end_at"
        value="{$form_end_at}">

    <input type="hidden" name="auto_extend"
        value="{$form_auto_extend}">

    <input type="hidden" name="confirm" value="1">

    <input type="submit" value="この内容で登録する">

</form>

<a href="goods-register.php">修正する</a>

HTML;

    exit;
}

// =============================================
// DB登録
// =============================================

$image_moved = false;

try {

    // トランザクション開始
    $pdo->beginTransaction();

    // オークションの場合、priceには開始価格を保存
    $register_price = $listing_type === 'auction'
        ? $start_price
        : $price;

    // 商品情報を登録
    $sql = <<<SQL
    INSERT INTO goods
    (seller_id, goods_name, description, price, `condition`, category_id, listed_at)
    VALUES
    (:seller_id, :goods_name, :description, :price, :goods_condition, :category_id, NOW())
    SQL;

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':seller_id' => $_SESSION['user_id'],
        ':goods_name' => $goods_name,
        ':description' => $description,
        ':price' => $register_price,
        ':goods_condition' => $goods_condition,
        ':category_id' => $category_id
    ]);

    // 商品ID
    $goods_id = $pdo->lastInsertId();

    // =============================================
    // オークション情報の登録
    // =============================================

    if ($listing_type === 'auction') {

        $sql_auction = <<<SQL
        INSERT INTO auctions
        (goods_id, start_price, current_price, buyout_price, start_at, end_at, auto_extend, status)
        VALUES
        (:goods_id, :start_price, :current_price, :buyout_price, NOW(), :end_at, :auto_extend, 'active')
        SQL;

        $stmt_auction = $pdo->prepare($sql_auction);

        $buyout_value = ($buyout_price !== '' && $buyout_price !== null)
            ? (int) $buyout_price
            : null;

        $stmt_auction->execute([
            ':goods_id' => $goods_id,
            ':start_price' => (int) $start_price,
            ':current_price' => (int) $start_price,
            ':buyout_price' => $buyout_value,
            ':end_at' => $end_at,
            ':auto_extend' => (int) $auto_extend
        ]);
    }

    // =============================================
    // 商品画像の登録
    // =============================================

    if ($image_uploaded) {

        // 本保存先
        $goods_dir = __DIR__ . '/../../uploads/goods/';

        if (!is_dir($goods_dir)) {
            if (!mkdir($goods_dir, 0755, true) && !is_dir($goods_dir)) {
                throw new Exception('画像保存フォルダを作成できません。');
            }
        }

        // 移動先
        $goods_path = $goods_dir . $file_name;

        if (!rename($upload_path, $goods_path)) {
            throw new Exception('画像の移動に失敗しました。');
        }

        $image_moved = true;

        // DB保存用パス
        $image_path = 'uploads/goods/' . $file_name;

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

    // DB登録確定
    $pdo->commit();

} catch (Throwable $e) {

    // エラーログ
    error_log(
        '商品登録エラー: ' . $e->getMessage() .
        ' in ' . $e->getFile() . ':' . $e->getLine() .
        ' [' . get_class($e) . ']'
    );

    // DBロールバック
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 移動済み画像を削除
    if (
        $image_moved &&
        isset($goods_path) &&
        is_file($goods_path)
    ) {
        unlink($goods_path);
    }

    // 一時画像を削除
    if (
        isset($upload_path) &&
        is_file($upload_path)
    ) {
        unlink($upload_path);
    }

    exit('商品登録に失敗しました。');
}

// =============================================
// 登録完了
// =============================================

// セッションの画像情報を削除
unset($_SESSION['goods_image']);

echo '商品情報を登録しました！';

echo <<<HTML

<p>
    <a href="goods-list.php">商品一覧を見る</a>
</p>

HTML;

?>
