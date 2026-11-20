<?php

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

// データベースに接続
$pdo = getPDO();

// 商品IDをURLから取得
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

// 商品IDが正しくない場合
if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    exit('商品IDが正しくありません。');
}

// 商品情報とオークション情報を取得
$sql = '
    SELECT
        g.goods_id,
        g.seller_id,
        g.goods_name,
        g.description,
        g.`condition`,
        g.listed_at,
        g.is_sold,
        a.auction_id,
        a.start_price,
        a.current_price,
        a.buyout_price,
        a.start_at,
        a.end_at,
        a.auto_extend,
        a.status,
        a.winner_user_id,
        a.winning_price
    FROM goods AS g
    INNER JOIN auctions AS a
        ON a.goods_id = g.goods_id
    WHERE g.goods_id = ?
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$goods_id]);

$auction = $stmt->fetch(PDO::FETCH_ASSOC);

// オークション商品が見つからない場合
if ($auction === false) {
    exit('オークション商品が見つかりません。');
}

// 出品者名を取得
$seller_stmt = $pdo->prepare('SELECT user_name FROM users WHERE user_id = ?');
$seller_stmt->execute([(int) $auction['seller_id']]);
$seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);
$seller_name = $seller ? htmlspecialchars($seller['user_name'], ENT_QUOTES, 'UTF-8') : '不明';

// 落札者名を取得
if ($auction['winner_user_id'] === null) {
    $winner_name = null;
} else {
    $winner_stmt = $pdo->prepare('SELECT user_name FROM users WHERE user_id = ?');
    $winner_stmt->execute([(int) $auction['winner_user_id']]);
    $winner = $winner_stmt->fetch(PDO::FETCH_ASSOC);
    $winner_name = $winner ? $winner['user_name'] : '不明';
}

// 商品画像を取得
$image_sql = '
    SELECT image_path
    FROM goods_images
    WHERE goods_id = ?
    ORDER BY display_order ASC, image_id ASC
';

$image_stmt = $pdo->prepare($image_sql);
$image_stmt->execute([$goods_id]);

$goods_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

// 商品画像のパスを検証
foreach ($goods_images as &$image) {
    $image_path = $image['image_path'] ?? '';

    if (
        !is_string($image_path) ||
        !preg_match(
            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
            $image_path
        ) ||
        !is_file(__DIR__ . '/../../' . $image_path)
    ) {
        $image['image_path'] = '';
    }
}
unset($image);

// 入札履歴を取得
$bid_history_sql = '
    SELECT
        ab.bid_price,
        ab.bid_at,
        u.user_name
    FROM auction_bids AS ab
    INNER JOIN users AS u
        ON u.user_id = ab.user_id
    WHERE ab.auction_id = ?
    ORDER BY ab.bid_id DESC, ab.bid_at DESC
';

$bid_history_stmt = $pdo->prepare($bid_history_sql);
$bid_history_stmt->execute([$auction['auction_id']]);

$bid_history = $bid_history_stmt->fetchAll(PDO::FETCH_ASSOC);

// 最低入札金額を決定（入札履歴0件：開始価格、2件目以降：現在価格＋100円）
if (empty($bid_history)) {
    $min_bid = (int) $auction['start_price'];
} else {
    $min_bid = (int) $auction['current_price'] + 100;
}

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// ログイン中のユーザーが出品者か確認
$is_owner = isset($_SESSION['user_id'])
    && (int) $_SESSION['user_id'] === (int) $auction['seller_id'];

// オークションの開始・終了状態を確認
$tz = new DateTimeZone('Asia/Tokyo');
$now = new DateTimeImmutable('now', $tz);
$start_at = new DateTimeImmutable($auction['start_at'], $tz);
$end_at = new DateTimeImmutable($auction['end_at'], $tz);
$is_started = $now >= $start_at;
$is_ended = $now >= $end_at;

// 入札可能か判定（開始前・終了後・売却済みは不可）
$can_bid = isset($_SESSION['user_id'])
    && !$is_owner
    && $auction['status'] === 'active'
    && (int) $auction['is_sold'] === 0
    && $is_started
    && !$is_ended;

?>

<?php require '../../meetory-header.php'; ?>

<!-- ページ固有のスタイルシートを読み込む（オークション詳細専用） -->
<link rel="stylesheet" href="../assets/css/auction-detail.css">

<main>

    <h1>オークション詳細</h1>

    <article>

        <!-- 商品画像 -->
        <div class="goods-images">

            <?php if (!empty($goods_images)): ?>

                <?php
                $has_valid_image = false;
                ?>

                <?php foreach ($goods_images as $image): ?>

                    <?php if ($image['image_path'] !== ''): ?>

                        <?php $has_valid_image = true; ?>

                        <img
                            src="/Meetory/<?= htmlspecialchars(
                                $image['image_path'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            alt="<?= htmlspecialchars(
                                $auction['goods_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            width="300"
                            loading="lazy"
                        >

                    <?php endif; ?>

                <?php endforeach; ?>

                <?php if (!$has_valid_image): ?>

                    <p>画像はありません</p>

                <?php endif; ?>

            <?php else: ?>

                <p>画像はありません</p>

            <?php endif; ?>

        </div>

        <h2>
            <?= htmlspecialchars($auction['goods_name'], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <p>
            商品名：
            <?= htmlspecialchars($auction['goods_name'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            出品者：
            <?= $seller_name ?>
        </p>

        <p>
            開始価格：
            <?= number_format((int) $auction['start_price']) ?>円
        </p>

        <p>
            現在価格：
            <span id="current-price"><?= number_format((int) $auction['current_price']) ?></span>円
        </p>

        <p>
            即決価格：
            <?= $auction['buyout_price'] !== null ? number_format((int) $auction['buyout_price']) . '円' : 'なし' ?>
        </p>

        <p>
            開始日時：
            <?= htmlspecialchars($auction['start_at'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            終了日時：
            <span id="end-time"><?= htmlspecialchars($auction['end_at'], ENT_QUOTES, 'UTF-8') ?></span>
        </p>

        <p>
            商品の状態：
            <?= htmlspecialchars($auction['condition'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <h3>商品説明</h3>

        <p>
            <?= nl2br(htmlspecialchars($auction['description'], ENT_QUOTES, 'UTF-8')) ?>
        </p>

        <?php if ($auction['status'] === 'sold'): ?>

            <p>落札成立</p>
            <p>
                落札者：
                <?= htmlspecialchars($winner_name ?? '不明', ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p>
                落札価格：
                <?= number_format((int) $auction['winning_price']) ?>円
            </p>

        <?php elseif ($auction['status'] === 'ended'): ?>

            <p>入札がなかったため、落札者はいません。</p>

        <?php elseif ((int) $auction['is_sold'] === 1 || $auction['status'] !== 'active'): ?>

            <p>このオークションは終了しています。</p>

        <?php endif; ?>

    </article>

    <hr>

    <h2>入札履歴</h2>

    <ul id="bid-history-list">
        <?php if (!empty($bid_history)): ?>

            <?php foreach ($bid_history as $bid): ?>

                <li>
                    <?= htmlspecialchars($bid['user_name'], ENT_QUOTES, 'UTF-8') ?>：
                    <?= number_format((int) $bid['bid_price']) ?>円
                    (<?= htmlspecialchars($bid['bid_at'], ENT_QUOTES, 'UTF-8') ?>)
                </li>

            <?php endforeach; ?>

        <?php endif; ?>
    </ul>

    <hr>

    <h2>入札する</h2>

    <!-- 入札結果メッセージ表示エリア -->
    <div id="bid-message" class="bid-message"></div>

    <?php if (!isset($_SESSION['user_id'])): ?>

        <p>入札するにはログインしてください。</p>

        <p>
            <a href="../user/login.php">ログイン</a>
        </p>

    <?php elseif ($is_owner): ?>

        <p>出品者は入札できません。</p>

    <?php elseif ((int) $auction['is_sold'] === 1 || $auction['status'] !== 'active'): ?>

        <p>このオークションは終了しています。</p>

    <?php elseif (!$is_started): ?>

        <p>オークションはまだ開始していません。</p>

    <?php elseif ($is_ended): ?>

        <p>オークションは終了しています。</p>

    <?php elseif (!$can_bid): ?>

        <p>入札できません。</p>

    <?php else: ?>

        <form
            id="bid-form"
            action="bid-process.php"
            method="post"
            class="bid-form"
        >

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="goods_id" value="<?= (int) $auction['goods_id'] ?>">

            <div class="form-group">
                <label for="bid_amount">入札金額（円）（最低<?= number_format($min_bid) ?>円〜）</label>
                <input
                    type="number"
                    id="bid_amount"
                    name="bid_amount"
                    min="<?= $min_bid ?>"
                    step="1"
                    required
                    value="<?= $min_bid ?>"
                >
            </div>

            <button
                type="submit"
                id="bid-submit-btn"
                class="bid-btn"
            >
                入札する
            </button>

        </form>

    <?php endif; ?>

    <hr>

    <p>
        <a href="../goods/goods-list.php">商品一覧に戻る</a>
    </p>

    <?php if (isset($_SESSION['user_id']) && !$is_owner): ?>
        <p>
            <a href="../reports/report.php?target_type=goods&amp;target_id=<?= (int) $auction['goods_id'] ?>">
                この商品を通報する
            </a>
        </p>
    <?php endif; ?>

</main>

<script src="../assets/js/auction-detail.js"></script>

<?php require '../../meetory-footer.php'; ?>
