
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

// user_idが指定されていない場合はログイン中のユーザーIDを使用
if (
    $profile_user_id === false ||
    $profile_user_id === null ||
    $profile_user_id <= 0
) {
    if (isset($_SESSION['user_id']) && is_int((int) $_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
        $profile_user_id = (int) $_SESSION['user_id'];
    } else {
        http_response_code(400);
        exit('ユーザーIDが正しくありません。');
    }
}

// ユーザー情報を取得
$sql = '
    SELECT
        user_id,
        user_name,
        created_at,
        profile_image_type,
        profile_image
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

$viewer_user_id = isset($_SESSION['user_id'])
    ? filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT)
    : false;
$is_own_profile = $viewer_user_id !== false
    && $viewer_user_id !== null
    && (int) $viewer_user_id === (int) $profile_user['user_id'];

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
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $profile_user_id
]);

$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active_goods_count_stmt = $pdo->prepare(
    'SELECT COUNT(*)
    FROM goods AS g
    WHERE g.seller_id = :seller_id
        AND g.is_sold = 0
        AND NOT EXISTS (
            SELECT 1
            FROM auctions AS a
            WHERE a.goods_id = g.goods_id
        )'
);
$active_goods_count_stmt->execute(['seller_id' => $profile_user_id]);
$active_goods_count = (int) $active_goods_count_stmt->fetchColumn();

$show_all_goods = isset($_GET['products']) && $_GET['products'] === 'all';
$goods_sql = '
    SELECT
        g.goods_id,
        g.goods_name,
        g.price,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM goods AS g
    WHERE g.seller_id = :seller_id
        AND g.is_sold = 0
        AND NOT EXISTS (
            SELECT 1
            FROM auctions AS a
            WHERE a.goods_id = g.goods_id
        )
    ORDER BY g.listed_at DESC, g.goods_id DESC';

if (!$show_all_goods) {
    $goods_sql .= ' LIMIT 4';
}

$goods_stmt = $pdo->prepare($goods_sql);
$goods_stmt->execute(['seller_id' => $profile_user_id]);
$listed_goods = $goods_stmt->fetchAll(PDO::FETCH_ASSOC);

$auction_stmt = $pdo->prepare(
    'SELECT
        g.goods_id,
        g.goods_name,
        a.current_price,
        a.end_at,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM goods AS g
    INNER JOIN auctions AS a
        ON a.goods_id = g.goods_id
    WHERE g.seller_id = :seller_id
        AND a.status = :status
        AND a.start_at <= NOW()
        AND a.end_at > NOW()
        AND g.is_sold = 0
    ORDER BY a.end_at ASC, g.goods_id DESC'
);
$auction_stmt->execute([
    'seller_id' => $profile_user_id,
    'status' => 'active'
]);
$active_auctions = $auction_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($listed_goods as &$goods) {
    $image_path = $goods['image_path'] ?? '';
    if (
        !is_string($image_path) ||
        !preg_match(
            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
            $image_path
        )
    ) {
        $goods['image_path'] = '';
    }
}
unset($goods);

foreach ($active_auctions as &$auction) {
    $image_path = $auction['image_path'] ?? '';
    if (
        !is_string($image_path) ||
        !preg_match(
            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
            $image_path
        )
    ) {
        $auction['image_path'] = '';
    }
}
unset($auction);

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

<link rel="stylesheet" href="../assets/css/profile.css">

<main class="profile-container">

    <section class="profile-header">
        <div class="profile-user-identity">
            <div class="profile-avatar">
                <?= renderProfileAvatar(
                    $profile_user,
                    '',
                    $profile_user['user_name'] . 'のプロフィールアイコン'
                ) ?>
            </div>
            <div>
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
            </div>
        </div>

        <?php if ($is_own_profile): ?>
            <p class="profile-owner-link">
                <a href="mypage.php">マイページへ戻る</a>
                <a href="profile-image.php">アイコンを変更する</a>
            </p>
        <?php endif; ?>
    </section>

    <section class="profile-rating">
        <h2>購入者からの評価</h2>

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
                        <?= $rating_counts[$rating] ?>件
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

    <section class="profile-reviews">
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

                <div class="review-more">

                    <button type="button" id="show-more-button">
                        もっと見る
                    </button>

                    <button type="button" id="collapse-button" hidden>
                        非表示にする
                    </button>

                </div>

            <?php endif; ?>

        <?php endif; ?>
    </section>

    <section class="profile-products" id="profile-products">
        <h2>出品中の商品</h2>

        <?php if (empty($listed_goods)): ?>
            <p>現在出品中の商品はありません。</p>
        <?php else: ?>
            <div class="profile-goods-list">
                <?php foreach ($listed_goods as $goods): ?>
                    <article class="profile-goods-card">
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $goods['goods_id'] ?>">
                            <?php if (!empty($goods['image_path'])): ?>
                                <img
                                    src="/Meetory/<?= htmlspecialchars(
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
                                <div class="profile-no-image">画像なし</div>
                            <?php endif; ?>
                            <h3><?= htmlspecialchars(
                                $goods['goods_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></h3>
                        </a>
                        <p class="profile-goods-price">
                            <?= number_format((int) $goods['price']) ?>円
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($active_goods_count > 4): ?>
                <p class="profile-products-more">
                    <?php if ($show_all_goods): ?>
                        <a href="profile.php?user_id=<?= (int) $profile_user_id ?>#profile-products">
                            4件に戻す
                        </a>
                    <?php else: ?>
                        <a href="profile.php?user_id=<?= (int) $profile_user_id ?>&amp;products=all#profile-products">
                            すべての商品を見る
                        </a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="profile-auctions" id="profile-auctions">
        <h2>開催中のオークション</h2>

        <?php if (empty($active_auctions)): ?>
            <p>開催中のオークションはありません。</p>
        <?php else: ?>
            <div class="profile-auction-list">
                <?php foreach ($active_auctions as $auction): ?>
                    <a
                        class="profile-auction-card"
                        href="../auction/auction-detail.php?goods_id=<?= (int) $auction['goods_id'] ?>"
                    >
                        <?php if (!empty($auction['image_path'])): ?>
                            <img
                                src="/Meetory/<?= htmlspecialchars(
                                    $auction['image_path'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $auction['goods_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <span class="profile-no-image">画像なし</span>
                        <?php endif; ?>
                        <span class="profile-auction-info">
                            <strong><?= htmlspecialchars(
                                $auction['goods_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></strong>
                            <span>現在価格：<?= number_format((int) $auction['current_price']) ?>円</span>
                            <span>終了日時：<?= htmlspecialchars(
                                date('Y/m/d H:i', strtotime($auction['end_at'])),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php require '../../meetory-footer.php'; ?>
<script src="../assets/js/profile.js"></script>