
<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログインしているか確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

// DB接続用の共通ファイルを読み込む
require_once '../../meetory-functions.php';

// ログイン中のユーザーIDを取得
$user_id = (int) $_SESSION['user_id'];

// DB接続
$pdo = getPDO();

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// 閲覧履歴を取得（browsing_historyとgoodsを結合）
$sql = '
    SELECT
        bh.history_id,
        bh.goods_id,
        bh.viewed_at,
        g.goods_name,
        g.price,
        g.`condition`,
        g.is_sold,
        (
            SELECT gi.image_path
            FROM goods_images AS gi
            WHERE gi.goods_id = g.goods_id
            ORDER BY gi.display_order ASC, gi.image_id ASC
            LIMIT 1
        ) AS image_path
    FROM browsing_history AS bh
    INNER JOIN goods AS g
        ON bh.goods_id = g.goods_id
    WHERE bh.user_id = ?
    ORDER BY bh.viewed_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);

$browsing_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 商品画像のパスを検証
foreach ($browsing_history as &$item) {
    $image_path = $item['image_path'] ?? '';

    if (
        !is_string($image_path) ||
        !preg_match(
            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
            $image_path
        )
    ) {
        $item['image_path'] = '';
    }
}
unset($item);

// 共通ヘッダー
require '../../meetory-header.php';

?>

<!-- ページ固有のスタイルシートを読み込む（閲覧履歴専用） -->
<link rel="stylesheet" href="../assets/css/favorites.css">

<main>

    <h1>閲覧履歴</h1>

    <?php if (empty($browsing_history)): ?>

        <p>閲覧履歴はまだありません。</p>

        <p>
            <a href="mypage.php">マイページに戻る</a>
        </p>

    <?php else: ?>

        <p>
            登録件数：
            <?= htmlspecialchars((string) count($browsing_history), ENT_QUOTES, 'UTF-8') ?> 件
        </p>

        <!-- 全件削除フォーム -->
        <form
            action="browsing-history-clear.php"
            method="post"
            class="clear-form"
            data-confirm
            data-confirm-message="閲覧履歴をすべて削除しますか？元に戻せません。"
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <button
                type="submit"
                class="clear-btn"
            >
                全件削除
            </button>
        </form>

        <div class="browsing-history-list">

        <?php foreach ($browsing_history as $item): ?>

            <article class="history-item">

                <div class="history-image">
                    <?php
                    $hist_image_path = $item['image_path'] ?? '';
                    $is_valid_hist_image = is_string($hist_image_path)
                        && preg_match(
                            '~\Auploads/goods/[a-zA-Z0-9_-]+\.(?:jpe?g|png|gif|webp)\z~i',
                            $hist_image_path
                        )
                        && is_file(__DIR__ . '/../../' . $hist_image_path);
                    ?>
                    <?php if ($is_valid_hist_image): ?>
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $item['goods_id'] ?>">
                            <img
                                src="/Meetory/<?= htmlspecialchars($hist_image_path, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($item['goods_name'], ENT_QUOTES, 'UTF-8') ?>"
                                width="200"
                                loading="lazy"
                            >
                        </a>
                    <?php else: ?>
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $item['goods_id'] ?>">
                            画像はありません
                        </a>
                    <?php endif; ?>
                </div>

                <div class="history-info">

                    <h2>
                        <a href="../goods/goods-detail.php?goods_id=<?= (int) $item['goods_id'] ?>">
                            <?= htmlspecialchars($item['goods_name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </h2>

                    <p>
                        価格：
                        <?= number_format((int) $item['price']) ?>円
                    </p>

                    <p>
                        商品の状態：
                        <?= htmlspecialchars($item['condition'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <?php if ((int) $item['is_sold'] === 1): ?>
                        <p class="history-sold">売却済み</p>
                    <?php else: ?>
                        <p class="history-active">出品中</p>
                    <?php endif; ?>

                    <p>
                        閲覧日時：
                        <?= htmlspecialchars(
                            date('Y年m月d日H:i:s', strtotime($item['viewed_at'])),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <!-- 個別削除フォーム -->
                    <form
                        action="browsing-history-delete.php"
                        method="post"
                        class="history-delete-form"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="history_id" value="<?= (int) $item['history_id'] ?>">
                        <button
                            type="submit"
                            class="history-delete-btn"
                            data-confirm
                            data-confirm-message="この履歴を削除しますか？"
                        >
                            削除
                        </button>
                    </form>

                </div>

                <hr>

            </article>

        <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <p>
        <a href="mypage.php">マイページに戻る</a>
    </p>

</main>

<!-- 閲覧履歴確認モーダル（アカウント削除確認と同じデザイン） -->
<div id="browsingConfirmOverlay" class="delete-account-modal-overlay"></div>
<div id="browsingConfirmModal" class="delete-account-modal">
    <div class="delete-account-modal-content">
        <h2 class="delete-account-modal-title">確認</h2>
        <p id="browsingConfirmMessage"></p>
        <div class="delete-account-modal-actions">
            <button
                type="button"
                class="delete-account-modal-btn delete-account-modal-cancel"
                id="browsingConfirmCancel"
            >
                いいえ
            </button>
            <button
                type="button"
                class="delete-account-modal-btn delete-account-modal-confirm"
                id="browsingConfirmConfirm"
            >
                はい
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('browsingConfirmOverlay');
    var modal = document.getElementById('browsingConfirmModal');
    var messageEl = document.getElementById('browsingConfirmMessage');
    var cancelBtn = document.getElementById('browsingConfirmCancel');
    var confirmBtn = document.getElementById('browsingConfirmConfirm');

    if (!overlay || !modal || !cancelBtn || !confirmBtn || !messageEl) {
        return;
    }

    var targetForm = null;

    function openModal(msg, form) {
        messageEl.textContent = msg;
        targetForm = form;
        overlay.classList.add('open');
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('open');
        modal.classList.remove('open');
        document.body.style.overflow = '';
        targetForm = null;
    }

    // data-confirm 付きフォームの submit をインターセプト
    var confirmForms = document.querySelectorAll('form[data-confirm]');
    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var msg = form.getAttribute('data-confirm-message');
            openModal(msg, form);
        });
    });

    // data-confirm 付きボタンのクリックをインターセプト
    var confirmButtons = document.querySelectorAll('button[data-confirm]');
    confirmButtons.forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var msg = btn.getAttribute('data-confirm-message');
            var form = btn.closest('form');
            openModal(msg, form);
        });
    });

    cancelBtn.addEventListener('click', function () {
        closeModal();
    });

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal();
        }
    });

    confirmBtn.addEventListener('click', function () {
        if (targetForm) {
            var form = targetForm;
            closeModal();
            form.submit();
        }
    });
})();
</script>

<?php require '../../meetory-footer.php'; ?>
