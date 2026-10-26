<?php

// セッション開始
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

// 共通処理
require_once '../../meetory-functions.php';

$pdo = getPDO();

$user_id = (int) $_SESSION['user_id'];

// 自分が参加しているメッセージを取得
// 購入前のDMと購入後の取引メッセージの両方に対応
$sql = '
    SELECT
        m.transaction_id,
        m.goods_id,
        m.message_id,
        m.sender_id,
        m.receiver_id,
        m.content,
        m.sent_at,

        partner.user_id AS partner_id,
        partner.user_name AS partner_name,

        CASE
            WHEN m.transaction_id IS NULL
                THEN pre_goods.goods_name
            ELSE (
                SELECT GROUP_CONCAT(
                    DISTINCT transaction_goods.goods_name
                    ORDER BY transaction_goods.goods_name
                    SEPARATOR ", "
                )
                FROM buy_items AS bi
                INNER JOIN goods AS transaction_goods
                    ON transaction_goods.goods_id = bi.goods_id
                WHERE bi.transaction_id = m.transaction_id
            )
        END AS goods_names

    FROM messages AS m

    INNER JOIN users AS partner
        ON partner.user_id = CASE
            WHEN m.sender_id = ?
                THEN m.receiver_id
            ELSE m.sender_id
        END

    LEFT JOIN goods AS pre_goods
        ON pre_goods.goods_id = m.goods_id

    WHERE
        (m.sender_id = ? OR m.receiver_id = ?)
        AND m.sender_id <> m.receiver_id

    ORDER BY
        m.sent_at DESC,
        m.message_id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $user_id,
    $user_id,
    $user_id
]);

$all_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 会話ごとに最新のメッセージだけを残す
$latest_conversations = [];

foreach ($all_messages as $message) {

    // 購入前のDMは「商品ID＋相手ID」で区別
    if ($message['transaction_id'] === null) {

        // 商品IDがない購入前メッセージは一覧に表示しない
        if (
            $message['goods_id'] === null ||
            (int) $message['goods_id'] <= 0
        ) {
            continue;
        }

        $conversation_key =
            'goods_'
            . (int) $message['goods_id']
            . '_'
            . (int) $message['partner_id'];

    } else {

        // 購入後の会話は「取引ID＋相手ID」で区別
        $conversation_key =
            'transaction_'
            . (int) $message['transaction_id']
            . '_'
            . (int) $message['partner_id'];
    }

    // SQLで新しいメッセージから取得しているため、
    // 最初に見つかったメッセージが最新の1件
    if (!isset($latest_conversations[$conversation_key])) {
        $latest_conversations[$conversation_key] = $message;
    }
}

// 共通ヘッダー
require '../../meetory-header.php';

?>

<h1>メッセージ一覧</h1>

<?php if (empty($latest_conversations)): ?>

    <p>まだメッセージはありません。</p>

<?php else: ?>

    <?php foreach ($latest_conversations as $message): ?>

        <section>

            <h2>
                会話相手：
                <?= htmlspecialchars(
                    $message['partner_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h2>

            <p>
                <?= $message['transaction_id'] === null
                    ? '商品への問い合わせ'
                    : '取引ID：' . (int) $message['transaction_id'] ?>
            </p>

            <p>
                商品：
                <?= htmlspecialchars(
                    $message['goods_names'] ?? '商品情報なし',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                最新メッセージ：
                <?= nl2br(
                    htmlspecialchars(
                        $message['content'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>
            </p>

            <p>
                送信日時：
                <?= htmlspecialchars(
                    $message['sent_at'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <?php if ($message['transaction_id'] === null): ?>

                <a href="message-list.php?goods_id=<?= (int) $message['goods_id'] ?>&amp;partner_id=<?= (int) $message['partner_id'] ?>">
                    この会話を開く
                </a>

            <?php else: ?>

                <a href="message-list.php?transaction_id=<?= (int) $message['transaction_id'] ?>&amp;partner_id=<?= (int) $message['partner_id'] ?>">
                    この会話を開く
                </a>

            <?php endif; ?>

        </section>

        <hr>

    <?php endforeach; ?>

<?php endif; ?>

<?php require '../../meetory-footer.php'; ?>