
<?php

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログイン確認
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

// 共通処理
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// URLから取引IDと会話相手IDを取得
$transaction_id = filter_input(
    INPUT_GET,
    'transaction_id',
    FILTER_VALIDATE_INT
);

$partner_id = filter_input(
    INPUT_GET,
    'partner_id',
    FILTER_VALIDATE_INT
);

// POSTの場合はフォームの値も受け取る
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = filter_input(
        INPUT_POST,
        'transaction_id',
        FILTER_VALIDATE_INT
    );

    $partner_id = filter_input(
        INPUT_POST,
        'partner_id',
        FILTER_VALIDATE_INT
    );
}

// IDの入力チェック
if (
    $transaction_id === false ||
    $transaction_id === null ||
    $transaction_id <= 0 ||
    $partner_id === false ||
    $partner_id === null ||
    $partner_id <= 0 ||
    $partner_id === $user_id
) {
    exit('取引IDまたは会話相手が正しくありません。');
}

// 購入者を取得
$sql = '
    SELECT buyer_id
    FROM buy
    WHERE transaction_id = ?
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id]);

$buyer_id = $stmt->fetchColumn();

if ($buyer_id === false) {
    exit('取引が見つかりません。');
}

$buyer_id = (int) $buyer_id;

// この取引に含まれる出品者IDを取得
$sql = '
    SELECT DISTINCT g.seller_id
    FROM buy_items AS bi
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    WHERE bi.transaction_id = ?
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id]);

$seller_ids = array_map(
    'intval',
    $stmt->fetchAll(PDO::FETCH_COLUMN)
);

// 会話相手が取引関係者か確認
$is_valid_conversation = false;

if ($user_id === $buyer_id) {
    // 購入者は、この取引の出品者と会話できる
    $is_valid_conversation = in_array(
        $partner_id,
        $seller_ids,
        true
    );
} else {
    // 出品者は、自分がこの取引の商品を出品している場合、
    // 購入者と会話できる
    $is_valid_conversation = (
        in_array($user_id, $seller_ids, true)
        && $partner_id === $buyer_id
    );
}

if (!$is_valid_conversation) {
    http_response_code(403);
    exit('この取引の相手とメッセージをやり取りする権限がありません。');
}

// CSRFトークン作成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// メッセージ送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf_token) ||
        !hash_equals($_SESSION['csrf_token'], $csrf_token)
    ) {
        http_response_code(403);
        exit('不正なリクエストです。画面を開き直してください。');
    }

    $content = $_POST['content'] ?? '';

    if (!is_string($content)) {
        exit('メッセージの形式が正しくありません。');
    }

    $content = trim($content);

    if ($content === '') {
        exit('メッセージを入力してください。');
    }

    if (mb_strlen($content, 'UTF-8') > 2000) {
        exit('メッセージは2000文字以内で入力してください。');
    }

    // 取引相手へのメッセージを保存
    $sql = '
        INSERT INTO messages (
            transaction_id,
            sender_id,
            receiver_id,
            content
        )
        VALUES (?, ?, ?, ?)
    ';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $transaction_id,
        $user_id,
        $partner_id,
        $content
    ]);

    // 二重送信防止のため画面を再表示
    header(
        'Location: message-list.php?transaction_id='
        . $transaction_id
        . '&partner_id='
        . $partner_id
    );
    exit;
}

// 会話相手の名前を取得
$sql = '
    SELECT user_name
    FROM users
    WHERE user_id = ?
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$partner_id]);

$partner_name = $stmt->fetchColumn();

if ($partner_name === false) {
    exit('会話相手が見つかりません。');
}

// この2人の会話だけを取得
$sql = '
    SELECT
        m.message_id,
        m.sender_id,
        m.content,
        m.sent_at,
        u.user_name AS sender_name
    FROM messages AS m
    INNER JOIN users AS u
        ON m.sender_id = u.user_id
    WHERE m.transaction_id = ?
        AND (
            (m.sender_id = ? AND m.receiver_id = ?)
            OR
            (m.sender_id = ? AND m.receiver_id = ?)
        )
    ORDER BY m.sent_at ASC, m.message_id ASC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $transaction_id,
    $user_id,
    $partner_id,
    $partner_id,
    $user_id
]);

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// この出品者との取引商品を取得
if ($user_id === $buyer_id) {
    $seller_id = $partner_id;
} else {
    $seller_id = $user_id;
}

$sql = '
    SELECT g.goods_name
    FROM buy_items AS bi
    INNER JOIN goods AS g
        ON bi.goods_id = g.goods_id
    WHERE bi.transaction_id = ?
        AND g.seller_id = ?
    ORDER BY bi.buy_item_id ASC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([$transaction_id, $seller_id]);

$goods_names = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 共通ヘッダー
require '../../meetory-header.php';

?>

<main>

    <h1>取引メッセージ</h1>

    <p>
        取引ID：
        <?= (int) $transaction_id ?>
    </p>

    <p>
        会話相手：
        <?= htmlspecialchars(
            $partner_name,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <h2>取引商品</h2>

    <?php foreach ($goods_names as $goods_name): ?>
        <p>
            <?= htmlspecialchars(
                $goods_name,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    <?php endforeach; ?>

    <h2>メッセージ一覧</h2>

    <?php if (empty($messages)): ?>

        <p>まだメッセージはありません。</p>

    <?php else: ?>

        <?php foreach ($messages as $message): ?>

            <section>
                <p>
                    <?= htmlspecialchars(
                        $message['sender_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                    （<?= htmlspecialchars(
                        $message['sent_at'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>）
                </p>

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $message['content'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>
                </p>
            </section>

            <hr>

        <?php endforeach; ?>

    <?php endif; ?>

    <h2>メッセージを送信</h2>

    <form action="message-list.php" method="post">

        <input
            type="hidden"
            name="transaction_id"
            value="<?= (int) $transaction_id ?>"
        >

        <input
            type="hidden"
            name="partner_id"
            value="<?= (int) $partner_id ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['csrf_token'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <label for="content">メッセージ本文</label>

        <textarea
            id="content"
            name="content"
            rows="5"
            maxlength="2000"
            required
        ></textarea>

        <p>2000文字以内で入力してください。</p>

        <button type="submit">送信する</button>

    </form>

</main>

<?php require '../../meetory-footer.php'; ?>