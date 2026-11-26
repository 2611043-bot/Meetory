<?php

// 管理者認証を共通化
require_once __DIR__ . '/includes/admin-auth.php';

$pdo = getPDO();

// 商品IDをURLから取得・検証
$goods_id = filter_input(INPUT_GET, 'goods_id', FILTER_VALIDATE_INT);

if ($goods_id === false || $goods_id === null || $goods_id <= 0) {
    require '../../meetory-header.php';
    ?>
    <main class="main-container admin-container">
        <div class="admin-layout">
            <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
            <div class="admin-main-content">
                <h1 class="admin-page-title">商品詳細</h1>
                <nav class="admin-breadcrumb">
                    <a href="index.php">ホーム</a>
                    <span class="admin-breadcrumb-separator">›</span>
                    <a href="goods-list.php">商品管理</a>
                    <span class="admin-breadcrumb-separator">›</span>
                    <span>商品詳細</span>
                </nav>
                <p>商品IDが正しくありません。</p>
                <p><a href="goods-list.php">商品一覧に戻る</a></p>
            </div>
        </div>
    </main>
    <?php
    require '../../meetory-footer.php';
    exit;
}

// 商品情報と出品者情報を取得
$stmt = $pdo->prepare(
    'SELECT
        g.goods_id,
        g.seller_id,
        g.goods_name,
        g.description,
        g.price,
        g.`condition`,
        g.listed_at,
        g.is_sold,
        g.is_active,
        u.user_id AS seller_user_id,
        u.user_name AS seller_name,
        u.profile_image_type,
        u.profile_image
    FROM goods AS g
    INNER JOIN users AS u
        ON u.user_id = g.seller_id
    WHERE g.goods_id = ?'
);
$stmt->execute([$goods_id]);
$goods = $stmt->fetch(PDO::FETCH_ASSOC);

// 商品が存在しない場合
if (!$goods) {
    require '../../meetory-header.php';
    ?>
    <main class="main-container admin-container">
        <div class="admin-layout">
            <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
            <div class="admin-main-content">
                <h1 class="admin-page-title">商品詳細</h1>
                <nav class="admin-breadcrumb">
                    <a href="index.php">ホーム</a>
                    <span class="admin-breadcrumb-separator">›</span>
                    <a href="goods-list.php">商品管理</a>
                    <span class="admin-breadcrumb-separator">›</span>
                    <span>商品詳細</span>
                </nav>
                <p>指定された商品は存在しません。</p>
                <p><a href="goods-list.php">商品一覧に戻る</a></p>
            </div>
        </div>
    </main>
    <?php
    require '../../meetory-footer.php';
    exit;
}

// 商品画像を取得
$image_stmt = $pdo->prepare(
    'SELECT image_path
    FROM goods_images
    WHERE goods_id = ?
    ORDER BY display_order ASC, image_id ASC'
);
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

// オークション情報を取得
$auction_stmt = $pdo->prepare(
    'SELECT
        a.auction_id,
        a.start_price,
        a.current_price,
        a.buyout_price,
        a.start_at,
        a.end_at,
        a.status,
        a.winner_user_id,
        a.winning_price,
        a.ended_at
    FROM auctions AS a
    WHERE a.goods_id = ?'
);
$auction_stmt->execute([$goods_id]);
$auction = $auction_stmt->fetch(PDO::FETCH_ASSOC);

// 落札者情報を取得（存在する場合）
$winner = null;
if ($auction && !empty($auction['winner_user_id'])) {
    $winner_stmt = $pdo->prepare(
        'SELECT user_id, user_name FROM users WHERE user_id = ?'
    );
    $winner_stmt->execute([(int) $auction['winner_user_id']]);
    $winner = $winner_stmt->fetch(PDO::FETCH_ASSOC);
}

// 購入・取引情報を取得
$purchase_stmt = $pdo->prepare(
    'SELECT
        b.transaction_id,
        b.buyer_id,
        b.total_price,
        b.purchased_at,
        b.completed_at,
        b.transaction_status,
        bi.purchase_price,
        u.user_id AS buyer_user_id,
        u.user_name AS buyer_name
    FROM buy AS b
    INNER JOIN buy_items AS bi
        ON bi.transaction_id = b.transaction_id
    INNER JOIN users AS u
        ON u.user_id = b.buyer_id
    WHERE bi.goods_id = ?
    ORDER BY b.purchased_at DESC'
);
$purchase_stmt->execute([$goods_id]);
$purchases = $purchase_stmt->fetchAll(PDO::FETCH_ASSOC);

// 削除可能判定
$canDelete = (int) $goods['is_sold'] === 0 && empty($purchases);
$deleteReason = '';

if ($canDelete && $auction) {
    // 入札履歴があるオークションは削除不可
    $bid_count_stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM auction_bids WHERE auction_id = ?'
    );
    $bid_count_stmt->execute([$auction['auction_id']]);
    $bid_count = (int) $bid_count_stmt->fetchColumn();
    if ($bid_count > 0) {
        $canDelete = false;
        $deleteReason = '入札履歴があるため削除できません。';
    }

    // 開催中のオークションは削除不可
    $is_holding = ($auction['status'] === 'active')
        && ($auction['start_at'] <= date('Y-m-d H:i:s'))
        && ($auction['end_at'] > date('Y-m-d H:i:s'));
    if ($is_holding || $auction['status'] === 'sold') {
        $canDelete = false;
        $deleteReason = 'オークション関連データがあるため削除できません。';
    }
}

// エスケープ関数
$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// ステータスラベル
$sold_label = (int) $goods['is_sold'] === 1 ? '売却済み' : '販売中';
$active_label = (int) $goods['is_active'] === 1 ? '公開中' : '公開停止';

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

<main class="main-container admin-container">
    <div class="admin-layout">
        <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <div class="admin-goods-detail">
        <a href="goods-list.php" class="admin-back-link">← 商品一覧に戻る</a>

        <h1 class="admin-page-title">商品詳細</h1>

        <nav class="admin-breadcrumb">
            <a href="index.php">ホーム</a>
            <span class="admin-breadcrumb-separator">›</span>
            <a href="goods-list.php">商品管理</a>
            <span class="admin-breadcrumb-separator">›</span>
            <span>商品詳細</span>
        </nav>

        <?php
        $flash_message = $_SESSION['goods_flash_message'] ?? '';
        $flash_type = $_SESSION['goods_flash_type'] ?? '';
        unset($_SESSION['goods_flash_message'], $_SESSION['goods_flash_type']);
        if ($flash_message !== ''): ?>
            <p class="admin-flash admin-flash-<?= $escape($flash_type) ?>" role="status"><?= $escape($flash_message) ?></p>
        <?php endif; ?>

        <!-- 商品情報 -->
        <section class="admin-detail-section">
            <h2>商品情報</h2>
            <div class="admin-detail-grid">
                <div class="admin-detail-item">
                    <span class="admin-detail-label">商品ID</span>
                    <span class="admin-detail-value"><?= (int) $goods['goods_id'] ?></span>
                </div>
                <div class="admin-detail-item">
                    <span class="admin-detail-label">商品名</span>
                    <span class="admin-detail-value"><?= $escape($goods['goods_name']) ?></span>
                </div>
                <div class="admin-detail-item">
                    <span class="admin-detail-label">価格</span>
                    <span class="admin-detail-value price"><?= number_format((int) $goods['price']) ?>円</span>
                </div>
                <div class="admin-detail-item">
                    <span class="admin-detail-label">商品状態</span>
                    <span class="admin-detail-value"><?= $escape($goods['condition']) ?></span>
                </div>
                <div class="admin-detail-item">
                    <span class="admin-detail-label">登録日時</span>
                    <span class="admin-detail-value"><?= $escape($goods['listed_at']) ?></span>
                </div>
                <div class="admin-detail-item">
                    <span class="admin-detail-label">公開状態</span>
                    <span class="admin-detail-badge <?= $active_label === '公開中' ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $escape($active_label) ?>
                    </span>
                </div>
                <div class="admin-detail-item">
                    <span class="admin-detail-label">販売状態</span>
                    <span class="admin-detail-badge badge-sold"><?= $escape($sold_label) ?></span>
                </div>
            </div>

            <!-- 公開状態変更ボタン -->
            <?php if ((int) $goods['is_sold'] === 0 && empty($purchases)): ?>
                <button
                    type="button"
                    id="goodsToggleBtn"
                    class="admin-toggle-btn admin-toggle-btn-<?= (int) $goods['is_active'] === 1 ? 'stop' : 'resume' ?>"
                    data-action="<?= (int) $goods['is_active'] === 1 ? 'stop' : 'resume' ?>"
                >
                    <?= (int) $goods['is_active'] === 1 ? '公開停止' : '再公開' ?>
                </button>

                <!-- 公開状態変更確認モーダル -->
                <div id="goodsToggleOverlay" class="delete-account-modal-overlay"></div>
                <div id="goodsToggleModal" class="delete-account-modal">
                    <div class="delete-account-modal-content">
                        <h2 class="delete-account-modal-title">公開状態の変更</h2>
                        <p id="goodsToggleMessage"></p>
                        <div class="delete-account-modal-actions">
                            <button
                                type="button"
                                class="delete-account-modal-btn delete-account-modal-cancel"
                                id="goodsToggleCancel"
                            >
                                キャンセル
                            </button>
                            <button
                                type="button"
                                class="delete-account-modal-btn delete-account-modal-confirm"
                                id="goodsToggleConfirm"
                            >
                                実行する
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 公開状態変更フォーム -->
                <form
                    id="goodsToggleForm"
                    action="goods-toggle-process.php"
                    method="post"
                    style="display: none;"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="goods_id" value="<?= (int) $goods['goods_id'] ?>">
                    <input type="hidden" name="action" id="goodsToggleAction" value="">
                </form>
            <?php endif; ?>

            <!-- 商品削除ボタン -->
            <?php if ($canDelete): ?>
                <button
                    type="button"
                    id="goodsDeleteBtn"
                    class="admin-toggle-btn admin-toggle-btn-delete"
                    data-goods-id="<?= (int) $goods['goods_id'] ?>"
                >
                    商品削除
                </button>

                <!-- 商品削除確認モーダル -->
                <div id="goodsDeleteOverlay" class="delete-account-modal-overlay"></div>
                <div id="goodsDeleteModal" class="delete-account-modal">
                    <div class="delete-account-modal-content">
                        <h2 class="delete-account-modal-title">商品削除の確認</h2>
                        <p id="goodsDeleteMessage">この商品を削除してもよろしいですか？\n削除すると元に戻せません。</p>
                        <div class="delete-account-modal-actions">
                            <button
                                type="button"
                                class="delete-account-modal-btn delete-account-modal-cancel"
                                id="goodsDeleteCancel"
                            >
                                キャンセル
                            </button>
                            <button
                                type="button"
                                class="delete-account-modal-btn delete-account-modal-confirm"
                                id="goodsDeleteConfirm"
                            >
                                削除する
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 商品削除フォーム -->
                <form
                    id="goodsDeleteForm"
                    action="goods-delete-process.php"
                    method="post"
                    style="display: none;"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="goods_id" value="<?= (int) $goods['goods_id'] ?>">
                </form>
            <?php elseif (!$canDelete && $deleteReason !== ''): ?>
                <p style="color: #dc2626; font-size: 13px; margin-top: 8px;"><?= $escape($deleteReason) ?></p>
            <?php endif; ?>

            <div style="margin-top: 16px;">
                <span class="admin-detail-label">商品説明</span>
                <div class="admin-detail-value" style="white-space: pre-wrap; word-break: break-word;">
                    <?= $escape($goods['description']) ?>
                </div>
            </div>
        </section>

        <!-- 商品画像 -->
        <section class="admin-detail-section">
            <h2>商品画像</h2>
            <?php if (!empty($goods_images)): ?>
                <div class="admin-image-gallery">
                    <?php foreach ($goods_images as $image): ?>
                        <?php if ($image['image_path'] !== ''): ?>
                            <div class="admin-image-item">
                                <img
                                    src="/Meetory/<?= $escape($image['image_path']) ?>"
                                    alt=""
                                    loading="lazy"
                                >
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-image-empty">画像はありません</div>
            <?php endif; ?>
        </section>

        <!-- 出品者情報 -->
        <section class="admin-detail-section">
            <h2>出品者情報</h2>
            <div class="admin-detail-grid">
                <div class="admin-detail-item">
                    <span class="admin-detail-label">ユーザーID</span>
                    <span class="admin-detail-value"><?= (int) $goods['seller_user_id'] ?></span>
                </div>
                <div class="admin-detail-item">
                    <span class="admin-detail-label">ユーザー名</span>
                    <span class="admin-detail-value"><?= $escape($goods['seller_name']) ?></span>
                </div>
                <div class="admin-detail-item" style="align-items: center;">
                    <span class="admin-detail-label">プロフィール画像</span>
                    <?php if (!empty($goods['profile_image'])): ?>
                        <img
                            src="data:<?= $escape($goods['profile_image_type']) ?>;base64,<?= $escape($goods['profile_image']) ?>"
                            alt=""
                            class="admin-profile-image"
                        >
                    <?php else: ?>
                        <span class="admin-detail-value">なし</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- オークション情報 -->
        <section class="admin-detail-section">
            <h2>オークション情報</h2>
            <?php if ($auction): ?>
                <div class="admin-detail-grid">
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">オークションID</span>
                        <span class="admin-detail-value"><?= (int) $auction['auction_id'] ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">開始価格</span>
                        <span class="admin-detail-value"><?= number_format((int) $auction['start_price']) ?>円</span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">現在価格</span>
                        <span class="admin-detail-value price"><?= number_format((int) $auction['current_price']) ?>円</span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">即決価格</span>
                        <span class="admin-detail-value"><?= $auction['buyout_price'] !== null ? number_format((int) $auction['buyout_price']) . '円' : 'なし' ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">開始日時</span>
                        <span class="admin-detail-value"><?= $escape($auction['start_at']) ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">終了日時</span>
                        <span class="admin-detail-value"><?= $escape($auction['end_at']) ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">オークション状態</span>
                        <span class="admin-detail-value"><?= $escape($auction['status']) ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">落札者ID</span>
                        <span class="admin-detail-value"><?= $winner ? (int) $winner['user_id'] : 'なし' ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">落札者名</span>
                        <span class="admin-detail-value"><?= $winner ? $escape($winner['user_name']) : 'なし' ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">落札価格</span>
                        <span class="admin-detail-value"><?= $auction['winning_price'] !== null ? number_format((int) $auction['winning_price']) . '円' : 'なし' ?></span>
                    </div>
                    <div class="admin-detail-item">
                        <span class="admin-detail-label">終了日時</span>
                        <span class="admin-detail-value"><?= $auction['ended_at'] !== null ? $escape($auction['ended_at']) : 'なし' ?></span>
                    </div>
                </div>
            <?php else: ?>
                <p>オークション情報はありません。</p>
            <?php endif; ?>
        </section>

        <!-- 購入・取引情報 -->
        <section class="admin-detail-section">
            <h2>購入・取引情報</h2>
            <?php if (!empty($purchases)): ?>
                <table class="admin-detail-table">
                    <thead>
                        <tr>
                            <th>取引ID</th>
                            <th>購入者ID</th>
                            <th>購入者名</th>
                            <th>購入価格</th>
                            <th>購入日時</th>
                            <th>取引状況</th>
                            <th>完了日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?= (int) $purchase['transaction_id'] ?></td>
                                <td><?= (int) $purchase['buyer_user_id'] ?></td>
                                <td><?= $escape($purchase['buyer_name']) ?></td>
                                <td><?= number_format((int) $purchase['purchase_price']) ?>円</td>
                                <td><?= $escape($purchase['purchased_at']) ?></td>
                                <td><?= $escape($purchase['transaction_status']) ?></td>
                                <td><?= $purchase['completed_at'] !== null ? $escape($purchase['completed_at']) : '－' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>購入履歴はありません。</p>
            <?php endif; ?>
        </section>
    </div>

        </div>
    </div>
</main>

<script>
(function () {
    var btn = document.getElementById('goodsToggleBtn');
    var overlay = document.getElementById('goodsToggleOverlay');
    var modal = document.getElementById('goodsToggleModal');
    var cancelBtn = document.getElementById('goodsToggleCancel');
    var confirmBtn = document.getElementById('goodsToggleConfirm');
    var form = document.getElementById('goodsToggleForm');
    var actionInput = document.getElementById('goodsToggleAction');
    var messageEl = document.getElementById('goodsToggleMessage');

    if (!btn || !overlay || !modal || !cancelBtn || !confirmBtn || !form) {
        return;
    }

    var messageMap = {
        stop: 'この商品を公開停止しますか？公開停止すると、一般ユーザーには商品が表示されなくなります。',
        resume: 'この商品を再公開しますか？再公開すると、一般ユーザーに商品が表示されるようになります。'
    };

    function openModal() {
        var action = btn.getAttribute('data-action');
        actionInput.value = action;
        messageEl.textContent = messageMap[action] || '';
        overlay.classList.add('open');
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('open');
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', function () {
        openModal();
    });

    cancelBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeModal();
    });

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal();
        }
    });

    confirmBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        form.submit();
    });
})();

// 商品削除確認モーダル
(function () {
    var btn = document.getElementById('goodsDeleteBtn');
    var overlay = document.getElementById('goodsDeleteOverlay');
    var modal = document.getElementById('goodsDeleteModal');
    var cancelBtn = document.getElementById('goodsDeleteCancel');
    var confirmBtn = document.getElementById('goodsDeleteConfirm');
    var form = document.getElementById('goodsDeleteForm');

    if (!btn || !overlay || !modal || !cancelBtn || !confirmBtn || !form) {
        return;
    }

    function openModal() {
        overlay.classList.add('open');
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('open');
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', function () {
        openModal();
    });

    cancelBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeModal();
    });

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal();
        }
    });

    confirmBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        form.submit();
    });
})();
</script>

<?php require '../../meetory-footer.php'; ?>
