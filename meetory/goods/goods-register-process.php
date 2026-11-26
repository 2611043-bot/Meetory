
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

// 商品名・商品説明
if (!is_string($goods_name) || !is_string($description)) {
    $errors[] = '商品名と商品説明を正しく入力してください。';
} else {
    $goods_name = trim($goods_name);
    $description = trim($description);

    if ($goods_name === '') {
        $errors[] = '商品名を入力してください。';
    } elseif (mb_strlen($goods_name, 'UTF-8') < 2) {
        $errors[] = '商品名は2文字以上で入力してください。';
    } elseif (mb_strlen($goods_name, 'UTF-8') > 100) {
        $errors[] = '商品名は100文字以内で入力してください。';
    }

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

// AIアシスト
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

    if (
        (!is_string($start_price) && !is_int($start_price)) ||
        !ctype_digit((string) $start_price) ||
        (int) $start_price < 1
    ) {
        $errors[] = '開始価格は1円以上の整数で入力してください。';
    }

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

    if (!is_string($end_at) || $end_at === '') {
        $errors[] = '終了日時を入力してください。';
    } else {
        $tz = new DateTimeZone('Asia/Tokyo');

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

    if (
        !is_string($auto_extend) ||
        !in_array($auto_extend, ['0', '1'], true)
    ) {
        $errors[] = '正しい自動延長設定を選択してください。';
    }

} elseif ($listing_type === 'normal') {

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
// 画像管理用設定
// =============================================

$temp_dir = __DIR__ . '/../../uploads/temp/';
$goods_dir = __DIR__ . '/../../uploads/goods/';

$allowed_extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];

$max_images = 3;
$max_size = 5 * 1024 * 1024;

// セッション画像を削除する関数
function deleteGoodsTempImages($images, $temp_dir)
{
    if (!is_array($images)) {
        return;
    }

    foreach ($images as $image) {
        $file_name = $image['file_name'] ?? '';

        if (
            is_string($file_name) &&
            preg_match('/\A[a-f0-9]{32}\.(jpg|png|gif|webp)\z/i', $file_name)
        ) {
            $path = $temp_dir . $file_name;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

// =============================================
// 新規出品時：前回の一時画像を削除
// =============================================

if ($confirm !== '1') {
    deleteGoodsTempImages(
        $_SESSION['goods_images'] ?? [],
        $temp_dir
    );

    unset($_SESSION['goods_images']);
}

// =============================================
// アップロードされた画像を配列に整理
// =============================================

$uploaded_images = [];

if (isset($_FILES['goods_images'])) {
    $files = $_FILES['goods_images'];

    if (
        !isset($files['name']) ||
        !is_array($files['name'])
    ) {
        exit('画像の送信形式が正しくありません。');
    }

    foreach ($files['name'] as $index => $name) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $uploaded_images[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $files['size'][$index] ?? 0
        ];
    }
}

if (count($uploaded_images) > $max_images) {
    exit('商品画像は最大3枚まで選択できます。');
}

// =============================================
// 新規出品時：画像を検証して一時保存
// =============================================

if ($confirm !== '1') {

    if (!empty($uploaded_images)) {

        if (!is_dir($temp_dir)) {
            if (!mkdir($temp_dir, 0755, true) && !is_dir($temp_dir)) {
                exit('画像保存フォルダを作成できません。');
            }
        }

        if (!class_exists('finfo')) {
            exit('画像の確認機能が利用できません。');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $saved_images = [];

        foreach ($uploaded_images as $image) {

            if ($image['error'] !== UPLOAD_ERR_OK) {
                deleteGoodsTempImages($saved_images, $temp_dir);
                exit('画像のアップロードに失敗しました。');
            }

            if (
                !is_string($image['tmp_name']) ||
                $image['tmp_name'] === '' ||
                !is_uploaded_file($image['tmp_name'])
            ) {
                deleteGoodsTempImages($saved_images, $temp_dir);
                exit('正しい画像ファイルをアップロードしてください。');
            }

            if (
                !is_int($image['size']) ||
                $image['size'] <= 0 ||
                $image['size'] > $max_size
            ) {
                deleteGoodsTempImages($saved_images, $temp_dir);
                exit('画像は1バイト以上、5MB以下にしてください。');
            }

            $mime_type = $finfo->file($image['tmp_name']);

            if (!is_string($mime_type) || !isset($allowed_extensions[$mime_type])) {
                deleteGoodsTempImages($saved_images, $temp_dir);
                exit('JPG、PNG、GIF、WebPの画像を使用してください。');
            }

            $extension = $allowed_extensions[$mime_type];
            $file_name = bin2hex(random_bytes(16)) . '.' . $extension;
            $upload_path = $temp_dir . $file_name;

            if (!move_uploaded_file($image['tmp_name'], $upload_path)) {
                deleteGoodsTempImages($saved_images, $temp_dir);
                exit('画像の保存に失敗しました。');
            }

            $saved_images[] = [
                'file_name' => $file_name,
                'image_path' => 'uploads/temp/' . $file_name
            ];
        }

        $_SESSION['goods_images'] = $saved_images;
    }
}

// =============================================
// 確認画面からの2回目の送信：画像を確認
// =============================================

$images = $_SESSION['goods_images'] ?? [];

if ($confirm === '1') {

    if (!is_array($images)) {
        exit('画像情報が正しくありません。もう一度出品してください。');
    }

    foreach ($images as $image) {

        $file_name = $image['file_name'] ?? '';

        if (
            !is_string($file_name) ||
            !preg_match('/\A[a-f0-9]{32}\.(jpg|png|gif|webp)\z/i', $file_name)
        ) {
            exit('画像情報が正しくありません。もう一度出品してください。');
        }

        $expected_path = 'uploads/temp/' . $file_name;

        if (($image['image_path'] ?? '') !== $expected_path) {
            exit('画像情報が正しくありません。もう一度出品してください。');
        }

        if (!is_file($temp_dir . $file_name)) {
            exit('一時保存した画像が見つかりません。もう一度出品してください。');
        }
    }
}

// =============================================
// 確認画面を表示
// =============================================

if ($confirm !== '1') {

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

    $display_listing_type = $listing_type === 'auction'
        ? 'オークション出品'
        : '通常出品';

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

    // 画像を複数表示
    $display_images = '';

    if (!empty($images)) {
        $display_images .= '<p>商品画像：</p>';

        foreach ($images as $index => $image) {
            $safe_path = htmlspecialchars(
                $image['image_path'],
                ENT_QUOTES,
                'UTF-8'
            );

            $display_images .= '<div style="display:inline-block;margin:8px;text-align:center;">';
            $display_images .= '<p>画像' . ($index + 1) . '</p>';
            $display_images .= '<img src="../../' . $safe_path . '" width="200" style="max-width:100%;height:auto;">';
            $display_images .= '</div>';
        }
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
{$display_images}

<form action="goods-register-process.php" method="post">

    <input type="hidden" name="csrf_token" value="{$form_csrf_token}">
    <input type="hidden" name="goods_name" value="{$form_goods_name}">
    <input type="hidden" name="description" value="{$form_description}">
    <input type="hidden" name="price" value="{$form_price}">
    <input type="hidden" name="goods_condition" value="{$form_goods_condition}">
    <input type="hidden" name="category_id" value="{$form_category_id}">
    <input type="hidden" name="listing_type" value="{$form_listing_type}">
    <input type="hidden" name="listing_assist_mode" value="{$form_listing_assist_mode}">
    <input type="hidden" name="start_price" value="{$form_start_price}">
    <input type="hidden" name="buyout_price" value="{$form_buyout_price}">
    <input type="hidden" name="end_at" value="{$form_end_at}">
    <input type="hidden" name="auto_extend" value="{$form_auto_extend}">
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

$moved_images = [];

try {

    $pdo->beginTransaction();

    $register_price = $listing_type === 'auction'
        ? $start_price
        : $price;

    // 商品情報
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

    $goods_id = $pdo->lastInsertId();

    // オークション情報
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
    // 複数商品画像の登録
    // =============================================

    if (!empty($images)) {

        if (!is_dir($goods_dir)) {
            if (!mkdir($goods_dir, 0755, true) && !is_dir($goods_dir)) {
                throw new Exception('画像保存フォルダを作成できません。');
            }
        }

        $sql_image = <<<SQL
        INSERT INTO goods_images
        (goods_id, image_path, display_order)
        VALUES
        (:goods_id, :image_path, :display_order)
        SQL;

        $stmt_image = $pdo->prepare($sql_image);

        foreach ($images as $index => $image) {

            $file_name = $image['file_name'];
            $temp_path = $temp_dir . $file_name;
            $destination_path = $goods_dir . $file_name;

            if (!rename($temp_path, $destination_path)) {
                throw new Exception('商品画像の移動に失敗しました。');
            }

            // 移動した画像を記録（エラー時の削除用）
            $moved_images[] = $destination_path;

            $image_path = 'uploads/goods/' . $file_name;

            $stmt_image->execute([
                ':goods_id' => $goods_id,
                ':image_path' => $image_path,
                ':display_order' => $index + 1
            ]);
        }
    }

    $pdo->commit();

} catch (Throwable $e) {

    error_log(
        '商品登録エラー: ' . $e->getMessage() .
        ' in ' . $e->getFile() . ':' . $e->getLine() .
        ' [' . get_class($e) . ']'
    );

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // 移動済み画像をすべて削除
    foreach ($moved_images as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    // 残っている一時画像も削除
    deleteGoodsTempImages($images, $temp_dir);

    unset($_SESSION['goods_images']);

    exit('商品登録に失敗しました。');
}

// =============================================
// 登録完了
// =============================================

deleteGoodsTempImages($images, $temp_dir);
unset($_SESSION['goods_images']);

echo '商品情報を登録しました！';

echo <<<HTML
<p>
    <a href="goods-list.php">商品一覧を見る</a>
</p>
HTML;

?>
