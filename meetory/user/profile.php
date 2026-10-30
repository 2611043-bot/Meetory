
<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// DB接続用の共通ファイルを読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();

// URLからプロフィールを表示するユーザーIDを取得
$profile_user_id = filter_input(
    INPUT_GET,
    'user_id',
    FILTER_VALIDATE_INT
);

if (
    $profile_user_id === false ||
    $profile_user_id === null ||
    $profile_user_id <= 0
) {
    http_response_code(400);
    exit('ユーザーIDが正しくありません。');
}

// ユーザー情報を取得
$sql = '
    SELECT
        user_id,
        user_name,
        created_at
    FROM users
    WHERE user_id = :user_id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $profile_user_id
]);

$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

// ユーザーが見つからない場合
if (!$profile_user) {
    http_response_code(404);
    exit('ユーザーが見つかりません。');
}

// 評価ごとの件数を取得
$sql = '
    SELECT
        rating,
        COUNT(*) AS review_count
    FROM reviews
    WHERE reviewee_id = :user_id
    GROUP BY rating
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $profile_user_id
]);

$rating_counts = [
    5 => 0,
    4 => 0,
    3 => 0,
    2 => 0,
    1 => 0
];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $rating = (int) $row['rating'];

    if (isset($rating_counts[$rating])) {
        $rating_counts[$rating] = (int) $row['review_count'];
    }
}

$total_reviews = array_sum($rating_counts);

// 最近のレビューを3件取得
$sql = '
    SELECT
        r.rating,
        r.comment,
        r.created_at,
        reviewer.user_name AS reviewer_name
    FROM reviews AS r
    INNER JOIN users AS reviewer
        ON r.reviewer_id = reviewer.user_id
    WHERE r.reviewee_id = :user_id
    ORDER BY r.created_at DESC, r.review_id DESC
    LIMIT 3
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $profile_user_id
]);

$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 評価表示用の情報
$rating_labels = [
    5 => 'すごくよかった',
    4 => '良かった',
    3 => '普通',
    2 => '悪かった',
    1 => 'とても悪かった'
];

$rating_faces = [
    5 => '😄',
    4 => '🙂',
    3 => '😐',
    2 => '🙁',
    1 => '😡'
];

$rating_colors = [
    5 => '#ec4899',
    4 => '#0ea5e9',
    3 => '#ca8a04',
    2 => '#f97316',
    1 => '#dc2626'
];

// 共通ヘッダー
require '../../meetory-header.php';

?>

<style>
    .profile-container {
        max-width: 760px;
        margin: 24px auto;
        padding: 16px;
    }

    .profile-header {
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 24px;
    }

    .rating-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
        margin: 16px 0 24px;
    }

    .rating-item {
        text-align: center;
        padding: 12px 4px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .rating-face {
        display: block;
        font-size: 28px;
    }

    .rating-count {
        display: block;
        font-size: 22px;
        font-weight: bold;
        margin: 4px 0;
    }

    .rating-label {
        font-size: 12px;
        overflow-wrap: anywhere;
    }

    .review-card {
        background: #f1f1f1;
        padding: 12px;
        margin-bottom: 10px;
        border-radius: 4px;
    }

    .review-heading {
        font-weight: bold;
        margin-bottom: 6px;
    }

    .review-comment {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        margin: 0;
    }

    .review-date {
        color: #666;
        font-size: 12px;
        margin-top: 8px;
    }

    .review-more {
        text-align: right;
    }

    @media (max-width: 480px) {
        .rating-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
</style>

<main class="profile-container">

    <section class="profile-header">
        <h1>
            <?= htmlspecialchars(
                $profile_user['user_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>

        <p>Meetory メンバー</p>

        <p>
            会員登録日：
            <?= htmlspecialchars(
                date('Y年m月d日', strtotime($profile_user['created_at'])),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    </section>

    <section>
        <h2>購入者からの評価</h2>

        <p>
            レビュー合計：
            <?= $total_reviews ?>件
        </p>

        <div class="rating-summary">

            <?php foreach ($rating_labels as $rating => $label): ?>

                <div class="rating-item">

                    <span
                        class="rating-face"
                        style="color: <?= $rating_colors[$rating] ?>;"
                        aria-label="<?= htmlspecialchars(
                            $label,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                        <?= $rating_faces[$rating] ?>
                    </span>

                    <span class="rating-count">
                        <?= $rating_counts[$rating] ?>
                    </span>

                    <span class="rating-label">
                        <?= htmlspecialchars(
                            $label,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>

            <?php endforeach; ?>

        </div>
    </section>

    <section>
        <h2>最近のレビュー</h2>

        <?php if (empty($reviews)): ?>

            <p>まだレビューはありません。</p>

        <?php else: ?>

            <?php foreach ($reviews as $review): ?>

                <?php $rating = (int) $review['rating']; ?>

                <article class="review-card">

                    <div
                        class="review-heading"
                        style="color: <?= $rating_colors[$rating] ?? '#333' ?>;"
                    >
                        <?= $rating_faces[$rating] ?? '' ?>

                        <?= htmlspecialchars(
                            $rating_labels[$rating] ?? '評価なし',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                    <?php if (
                        $review['comment'] !== null &&
                        $review['comment'] !== ''
                    ): ?>

                        <p class="review-comment"><?= htmlspecialchars(
                            $review['comment'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></p>

                    <?php else: ?>

                        <p class="review-comment">コメントはありません。</p>

                    <?php endif; ?>

                    <p class="review-date">
                        投稿者：
                        <?= htmlspecialchars(
                            $review['reviewer_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        <?= htmlspecialchars(
                            date(
                                'Y年m月d日',
                                strtotime($review['created_at'])
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                </article>

            <?php endforeach; ?>

            <?php if ($total_reviews > 3): ?>

                <p class="review-more">
                    <a href="profile-reviews.php?user_id=<?= (int) $profile_user_id ?>">
                        レビューをもっと見る
                    </a>
                </p>

            <?php endif; ?>

        <?php endif; ?>
    </section>

    <p>
        <a href="mypage.php">マイページへ戻る</a>
    </p>

</main>


<?php

// 現在選択されているタブを取得
$active_tab = $_GET['tab'] ?? 'selling';

// 不正な値が指定された場合は出品中にする
if (!in_array($active_tab, ['selling', 'sold'], true)) {
    $active_tab = 'selling';
}

// 出品中・売却済みの商品を取得
$sql = '
    SELECT
        g.goods_id,
        g.goods_name,
        g.price,
        g.is_sold,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM goods AS g
    WHERE g.seller_id = :seller_id
        AND g.is_sold = :is_sold
    ORDER BY g.listed_at DESC, g.goods_id DESC
';

$stmt = $pdo->prepare($sql);

// 出品中の商品
$stmt->execute([
    'seller_id' => $profile_user_id,
    'is_sold' => 0
]);

$listed_goods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 売却済みの商品
$stmt->execute([
    'seller_id' => $profile_user_id,
    'is_sold' => 1
]);

$sold_goods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 選択中のタブに応じて表示商品を決定
$display_goods = ($active_tab === 'selling')
    ? $listed_goods
    : $sold_goods;

?>

<section class="profile-products">

    <h2>出品商品</h2>

    <div class="profile-tabs">

        <a
            href="profile.php?user_id=<?= (int) $profile_user_id ?>&tab=selling"
            class="<?= $active_tab === 'selling' ? 'active' : '' ?>"
        >
            出品中（<?= count($listed_goods) ?>）
        </a>

        <a
            href="profile.php?user_id=<?= (int) $profile_user_id ?>&tab=sold"
            class="<?= $active_tab === 'sold' ? 'active' : '' ?>"
        >
            売却済み（<?= count($sold_goods) ?>）
        </a>

    </div>

    <?php if (empty($display_goods)): ?>

        <?php if ($active_tab === 'selling'): ?>

            <p>現在、出品中の商品はありません。</p>

        <?php else: ?>

            <p>売却済みの商品はありません。</p>

        <?php endif; ?>

    <?php else: ?>

        <div class="profile-goods-list">

            <?php foreach ($display_goods as $goods): ?>

                <article class="profile-goods-card">

                    <a href="../goods/goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>">

                        <?php if (!empty($goods['image_path'])): ?>

                            <img
                                src="/meetory_php/<?= htmlspecialchars(
                                    ltrim($goods['image_path'], '/'),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $goods['goods_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <p>画像はありません</p>

                        <?php endif; ?>

                        <h3>
                            <?= htmlspecialchars(
                                $goods['goods_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h3>

                    </a>

                    <p class="profile-goods-price">
                        <?= number_format((int) $goods['price']) ?>円
                    </p>

                    <?php if ((int) $goods['is_sold'] === 1): ?>

                        <span class="sold-out-label">SOLD OUT</span>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<style>

    .profile-products {
        margin: 30px 0;
    }

    .profile-tabs {
        display: flex;
        gap: 10px;
        margin: 16px 0;
        border-bottom: 1px solid #ddd;
    }

    .profile-tabs a {
        flex: 1;
        padding: 12px;
        text-align: center;
        text-decoration: none;
        color: #555;
        border-bottom: 3px solid transparent;
    }

    .profile-tabs a.active {
        color: #222;
        font-weight: bold;
        border-bottom-color: #333;
    }

    .profile-goods-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .profile-goods-card {
        min-width: 0;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .profile-goods-card img {
        display: block;
        width: 100%;
        height: 180px;
        object-fit: contain;
    }

    .profile-goods-card a {
        color: inherit;
        text-decoration: none;
    }

    .profile-goods-card h3 {
        overflow-wrap: anywhere;
    }

    .profile-goods-price {
        font-weight: bold;
    }

    .sold-out-label {
        display: inline-block;
        padding: 5px 10px;
        background: #555;
        color: white;
        font-size: 12px;
        font-weight: bold;
        border-radius: 4px;
    }

    @media (max-width: 480px) {

        .profile-goods-list {
            grid-template-columns: 1fr;
        }

    }

</style>

<?php require '../../meetory-footer.php'; ?>