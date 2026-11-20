
<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../user/login.php');
    exit;
}

// DB接続
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// CSRFトークンを作成
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// URLから取引IDを取得
$transaction_id = filter_input(
    INPUT_GET,
    'transaction_id',
    FILTER_VALIDATE_INT
);

if (
    $transaction_id === false ||
    $transaction_id === null ||
    $transaction_id <= 0
) {
    exit('取引IDが正しくありません。');
}

// 取引情報と購入者情報を取得
$sql = '
    SELECT
        b.transaction_id,
        b.buyer_id,
        b.total_price,
        b.purchased_at,
        b.transaction_status,
        b.completed_at,
        b.cancelled_at,
        b.cancelled_by,
        b.cancel_reason,
        b.shipping_name,
        b.shipping_postal_code,
        b.shipping_prefecture,
        b.shipping_city,
        b.shipping_address_line,
        b.shipping_building,
        b.shipping_phone,
        buyer.user_name AS buyer_name,
        buyer.profile_image_type,
        buyer.profile_image
    FROM buy AS b
    INNER JOIN users AS buyer
        ON b.buyer_id = buyer.user_id
    WHERE b.transaction_id = :transaction_id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'transaction_id' => $transaction_id
]);

$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

// 取引が存在しない場合
if (!$transaction) {
    exit('取引情報が見つかりません。');
}

// 取引に含まれる商品と出品者を取得
$sql = '
    SELECT
        g.goods_id,
        g.goods_name,
        g.seller_id,
        seller.user_name AS seller_name,
        seller.profile_image_type,
        seller.profile_image,
        bi.purchase_price
    FROM buy_items AS bi
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    INNER JOIN users AS seller
        ON g.seller_id = seller.user_id
    WHERE bi.transaction_id = :transaction_id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'transaction_id' => $transaction_id
]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ログイン中のユーザーが購入者または出品者か確認
$is_buyer = (int) $transaction['buyer_id'] === $user_id;
$is_seller = false;
$seller_ids = [];
foreach ($items as $item) {
    $sid = (int) $item['seller_id'];
    $seller_ids[$sid] = true;
    if ($sid === $user_id) {
        $is_seller = true;
    }
}
$is_seller_of_all = $is_seller && count($seller_ids) === 1;

if (!$is_buyer && !$is_seller) {
    exit('この取引を確認する権限がありません。');
}

// 共通ヘッダー
require_once '../../meetory-header.php';

?>

<link rel="stylesheet" href="../assets/css/transaction-detail.css">

<h1>取引詳細</h1>

<p>
    取引ID：
    <?= (int) $transaction['transaction_id'] ?>
</p>

<p>
    取引状況：
    <?= htmlspecialchars(
        $transaction['transaction_status'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    購入日時：
    <?= htmlspecialchars(
        date('Y年m月d日 H:i:s', strtotime($transaction['purchased_at'])),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    購入者：
    <span class="transaction-user">
        <span class="transaction-user-avatar">
            <?= renderProfileAvatar(
                $transaction,
                '',
                $transaction['buyer_name'] . 'のプロフィールアイコン'
            ) ?>
        </span>
        <?= htmlspecialchars($transaction['buyer_name'], ENT_QUOTES, 'UTF-8') ?>
    </span>
</p>

<h2>商品情報</h2>

<?php if (empty($items)): ?>

    <p>商品情報が見つかりません。</p>

<?php else: ?>

    <?php foreach ($items as $item): ?>

        <section>
            <h3>
                <?= htmlspecialchars(
                    $item['goods_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h3>

            <p>
                出品者：
                <span class="transaction-user">
                    <span class="transaction-user-avatar">
                        <?= renderProfileAvatar(
                            $item,
                            '',
                            $item['seller_name'] . 'のプロフィールアイコン'
                        ) ?>
                    </span>
                    <?= htmlspecialchars($item['seller_name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </p>

            <p>
                購入時の価格：
                <?= number_format((int) $item['purchase_price']) ?>円
            </p>
        </section>

        <hr>

        <?php endforeach; ?>

    <?php endif; ?>

    <?php
    // 配送情報を取得（取引詳細から参照）
    $stmt = $pdo->prepare(
        'SELECT d.*, s.user_name AS seller_name
        FROM deliveries AS d
        INNER JOIN users AS s
            ON s.user_id = d.seller_id
        WHERE d.transaction_id = :transaction_id'
        . ($is_seller && !$is_buyer ? ' AND d.seller_id = :seller_id' : '')
    );

    if ($is_seller && !$is_buyer) {
        $stmt->execute([
            'transaction_id' => $transaction_id,
            'seller_id' => $user_id
        ]);
    } else {
        $stmt->execute(['transaction_id' => $transaction_id]);
    }

    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (!empty($deliveries)): ?>

        <h2>配送情報</h2>

        <?php foreach ($deliveries as $delivery): ?>

            <section>
                <h3>
                    <?= htmlspecialchars($delivery['seller_name'], ENT_QUOTES, 'UTF-8') ?>
                    の配送
                </h3>

                <p>
                    配送業者：
                    <?= htmlspecialchars(
                        $delivery['carrier'] === 'その他'
                            ? ($delivery['carrier_other'] ?? 'その他')
                            : $delivery['carrier'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    追跡番号：
                    <?= htmlspecialchars($delivery['tracking_number'], ENT_QUOTES, 'UTF-8') ?>
                </p>

                <p>
                    発送日時：
                    <?php if ($delivery['shipped_at'] !== null): ?>
                        <?= htmlspecialchars(
                            date('Y年m月d日 H:i:s', strtotime($delivery['shipped_at'])),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </p>

                <p>
                    配送ステータス：
                    <?php
                    $statusLabels = [
                        'awaiting_shipment' => '発送待ち',
                        'shipped' => '発送済み',
                        'received' => '受取確認済み'
                    ];
                    echo htmlspecialchars(
                        $statusLabels[$delivery['delivery_status']] ?? $delivery['delivery_status'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </p>
            </section>

            <hr>

        <?php endforeach; ?>

    <?php elseif ($is_buyer || $is_seller): ?>

        <p>配送情報はまだ登録されていません。</p>

    <?php endif; ?>

<p>
    合計金額：
    <?= number_format((int) $transaction['total_price']) ?>円
</p>

<?php if ($transaction['completed_at'] !== null): ?>

    <p>
        取引完了日時：
        <?= htmlspecialchars(
            date('Y年m月d日 H:i:s', strtotime($transaction['completed_at'])),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($transaction['cancelled_at'])): ?>

    <p>
        キャンセル日時：
        <?= htmlspecialchars(
            date('Y年m月d日 H:i:s', strtotime($transaction['cancelled_at'])),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <?php if (!empty($transaction['cancel_reason'])): ?>
        <p>
            キャンセル理由：
            <?= nl2br(htmlspecialchars($transaction['cancel_reason'], ENT_QUOTES, 'UTF-8')) ?>
        </p>
    <?php endif; ?>

<?php endif; ?>

<?php if ($is_buyer): ?>

    <p>あなたはこの取引の購入者です。</p>

    <section>
        <h2>配送先情報</h2>

        <?php if ($transaction['transaction_status'] === '購入済み'): ?>

            <p>
                配送先氏名：
                <?= !empty($transaction['shipping_name']) ? htmlspecialchars($transaction['shipping_name'], ENT_QUOTES, 'UTF-8') : '未設定' ?>
            </p>
            <p>
                郵便番号：
                <?= !empty($transaction['shipping_postal_code']) ? htmlspecialchars($transaction['shipping_postal_code'], ENT_QUOTES, 'UTF-8') : '未設定' ?>
            </p>
            <p>
                都道府県：
                <?= !empty($transaction['shipping_prefecture']) ? htmlspecialchars($transaction['shipping_prefecture'], ENT_QUOTES, 'UTF-8') : '未設定' ?>
            </p>
            <p>
                市区町村：
                <?= !empty($transaction['shipping_city']) ? htmlspecialchars($transaction['shipping_city'], ENT_QUOTES, 'UTF-8') : '未設定' ?>
            </p>
            <p>
                町名・番地：
                <?= !empty($transaction['shipping_address_line']) ? htmlspecialchars($transaction['shipping_address_line'], ENT_QUOTES, 'UTF-8') : '未設定' ?>
            </p>
            <?php if (!empty($transaction['shipping_building'])): ?>
                <p>
                    建物名・部屋番号：
                    <?= htmlspecialchars($transaction['shipping_building'], ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>
            <p>
                電話番号：
                <?= !empty($transaction['shipping_phone']) ? htmlspecialchars($transaction['shipping_phone'], ENT_QUOTES, 'UTF-8') : '未設定' ?>
            </p>

        <?php elseif ($transaction['transaction_status'] === '発送済み' || $transaction['transaction_status'] === '取引完了'): ?>

            <p>配送先情報は購入時の内容で固定されています。</p>

        <?php endif; ?>
    </section>

    <?php if ($transaction['transaction_status'] === '購入済み'): ?>

        <form action="cancel-confirm.php" method="post" class="cancel-action-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="transaction_id" value="<?= (int) $transaction['transaction_id'] ?>">
            <button type="submit" class="cancel-btn">取引をキャンセルする</button>
        </form>

    <?php elseif ($transaction['transaction_status'] === '発送済み'): ?>

        <button
            type="button"
            id="completeBtn"
            class="complete-btn"
        >
            取引を完了する
        </button>

        <!-- 取引完了確認モーダル（アカウント削除確認と同じデザイン） -->
        <div id="transactionCompleteOverlay" class="delete-account-modal-overlay"></div>
        <div id="transactionCompleteModal" class="delete-account-modal">
            <div class="delete-account-modal-content">
                <h2 class="delete-account-modal-title">取引完了の確認</h2>
                <p>この取引を完了してもよろしいですか？</p>
                <p style="font-size: 14px; color: #666; margin-top: 8px;">取引を完了すると、取引状態が「取引完了」に変更されます。</p>
                <div class="delete-account-modal-actions">
                    <button
                        type="button"
                        class="delete-account-modal-btn delete-account-modal-cancel"
                        id="completeCancel"
                    >
                        いいえ
                    </button>
                    <button
                        type="button"
                        class="delete-account-modal-btn delete-account-modal-confirm"
                        id="completeConfirm"
                    >
                        はい
                    </button>
                </div>
            </div>
        </div>

        <form
            id="completeForm"
            action="complete-process.php"
            method="post"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <input
                type="hidden"
                name="transaction_id"
                value="<?= (int) $transaction['transaction_id'] ?>"
            >
        </form>

        <script src="../assets/js/transaction-detail.js"></script>

    <?php elseif ($transaction['transaction_status'] === '取引完了'): ?>

        <p>この取引は完了しています。</p>

    <?php endif; ?>

    <?php if ($transaction['transaction_status'] === '取引完了'): ?>

        <?php
        $review_sql = '
            SELECT COUNT(*)
            FROM reviews
            WHERE transaction_id = :transaction_id
        ';

        $review_stmt = $pdo->prepare($review_sql);
        $review_stmt->execute([
            'transaction_id' => $transaction['transaction_id']
        ]);

        $has_review = (int) $review_stmt->fetchColumn() > 0;
        ?>

        <?php if ($has_review): ?>

            <p>この取引は評価済みです。</p>

        <?php else: ?>

            <p>
                <a href="../reviews/review-form.php?transaction_id=<?= (int) $transaction['transaction_id'] ?>">
                    販売者を評価する
                </a>
            </p>

        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>

    <?php if ($is_seller): ?>

    <p>あなたはこの取引の出品者です。</p>

    <?php if ($transaction['transaction_status'] === '購入済み'): ?>

        <section>
            <h2>発送手続き</h2>
            <p>この取引の商品を発送します。配送業者と追跡番号を入力して「発送完了」ボタンを押してください。</p>

            <form action="shipping-register-process.php" method="post" class="shipping-form">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="transaction_id" value="<?= (int) $transaction['transaction_id'] ?>">
                <input type="hidden" name="return_to" value="transaction-detail">

                <div class="form-group">
                    <label for="carrier">配送業者</label><br>
                    <select id="carrier" name="carrier" required>
                        <option value="">選択してください</option>
                        <option value="ヤマト運輸">ヤマト運輸</option>
                        <option value="日本郵便">日本郵便</option>
                        <option value="佐川急便">佐川急便</option>
                        <option value="その他">その他</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="carrier_other">配送業者名（その他を選択した場合のみ入力）</label><br>
                    <input
                        type="text"
                        id="carrier_other"
                        name="carrier_other"
                        maxlength="100"
                    >
                </div>

                <div class="form-group">
                    <label for="tracking_number">追跡番号</label><br>
                    <input
                        type="text"
                        id="tracking_number"
                        name="tracking_number"
                        maxlength="100"
                        required
                    >
                    <p class="hint">追跡番号の桁数は配送業者によって異なります。入力された値をそのまま保存します。</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-ship">発送完了</button>
                </div>

            </form>
        </section>

        <?php if ($is_seller_of_all): ?>
            <form action="cancel-confirm.php" method="post" class="cancel-action-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="transaction_id" value="<?= (int) $transaction['transaction_id'] ?>">
                <button type="submit" class="cancel-btn">取引をキャンセルする</button>
            </form>
        <?php endif; ?>

    <?php elseif ($transaction['transaction_status'] === '発送済み'): ?>

        <section>
            <h2>配送情報</h2>
            <p>この取引は発送済みです。</p>
        </section>

    <?php elseif ($transaction['transaction_status'] === '取引完了'): ?>

        <p>この取引は完了しています。</p>

    <?php elseif ($transaction['transaction_status'] === 'キャンセル済み'): ?>

        <p>この取引はキャンセル済みです。</p>

    <?php endif; ?>

<?php endif; ?>

<p>
    <a href="../messages/message-inbox.php">メッセージ一覧へ</a>
</p>

<p>
    <a href="../user/mypage.php">マイページへ戻る</a>
</p>

<?php require_once '../../meetory-footer.php'; ?>