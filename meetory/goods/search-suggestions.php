<?php
require_once '../../meetory-functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$query = $_GET['q'] ?? '';
if (!is_string($query)) {
    http_response_code(400);
    echo json_encode(['error' => '検索語が正しくありません。']);
    exit;
}

$query = trim($query);
if ($query === '') {
    echo json_encode([
        'products' => [],
        'categories' => []
    ]);
    exit;
}

$product_limit = $_GET['limit'] ?? '5';
if (!is_string($product_limit) || !in_array($product_limit, ['3', '5'], true)) {
    http_response_code(400);
    echo json_encode(['error' => '候補件数が正しくありません。']);
    exit;
}
$product_limit = (int) $product_limit;

if (mb_strlen($query, 'UTF-8') > 100) {
    http_response_code(400);
    echo json_encode(['error' => '検索語は100文字以内で入力してください。']);
    exit;
}

$pdo = getPDO();
$keyword = '%' . $query . '%';

$product_stmt = $pdo->prepare(
    'SELECT
        g.goods_id,
        g.goods_name,
        g.price,
        g.category_id,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM goods AS g
    WHERE (
            g.goods_name LIKE :product_keyword
            OR EXISTS (
                SELECT 1
                FROM categories AS c
                WHERE c.category_id = g.category_id
                    AND c.category_name LIKE :category_keyword
            )
        )
        AND g.is_sold = 0
        AND NOT EXISTS (
            SELECT 1
            FROM auctions AS a
            WHERE a.goods_id = g.goods_id
        )
    ORDER BY g.listed_at DESC, g.goods_id DESC
    LIMIT ' . $product_limit
);
$product_stmt->execute([
    ':product_keyword' => $keyword,
    ':category_keyword' => $keyword
]);
$products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);

$product_category_ids = [];
foreach ($products as &$product) {
    if (isset($product['category_id']) && ctype_digit((string) $product['category_id'])) {
        $product_category_ids[] = (int) $product['category_id'];
    }

    $image_path = $product['image_path'] ?? '';
    if (
        !is_string($image_path) ||
        !preg_match(
            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
            $image_path
        )
    ) {
        $product['image_path'] = '';
    }
    unset($product['category_id']);
}
unset($product);

$category_stmt = $pdo->prepare(
    'SELECT c.category_id, c.category_name
    FROM categories AS c
    WHERE c.category_name LIKE :keyword
    ORDER BY c.category_name ASC, c.category_id ASC
    LIMIT 3'
);
$category_stmt->execute([':keyword' => $keyword]);
$direct_categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

$related_categories_by_id = [];
$product_category_ids = array_values(array_unique($product_category_ids));
if ($product_category_ids !== []) {
    $placeholders = implode(',', array_fill(0, count($product_category_ids), '?'));
    $related_category_stmt = $pdo->prepare(
        'SELECT category_id, category_name
        FROM categories
        WHERE category_id IN (' . $placeholders . ')'
    );
    $related_category_stmt->execute($product_category_ids);
    foreach ($related_category_stmt->fetchAll(PDO::FETCH_ASSOC) as $category) {
        $related_categories_by_id[(int) $category['category_id']] = $category;
    }
}

$categories = [];
$included_category_ids = [];
foreach ($direct_categories as $category) {
    $category_id = (int) $category['category_id'];
    $categories[] = $category;
    $included_category_ids[$category_id] = true;
}

foreach ($product_category_ids as $category_id) {
    if (count($categories) >= 3) {
        break;
    }
    if (
        !isset($included_category_ids[$category_id]) &&
        isset($related_categories_by_id[$category_id])
    ) {
        $categories[] = $related_categories_by_id[$category_id];
        $included_category_ids[$category_id] = true;
    }
}

echo json_encode(
    [
        'products' => $products,
        'categories' => $categories
    ],
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
