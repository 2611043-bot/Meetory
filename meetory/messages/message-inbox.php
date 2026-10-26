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

// 自分が参加している会話の最新メッセージを取得
$sql = '
    SELECT
        m.transaction_id,
        m.message_id,
        m.sender_id,
        m.receiver_id,
        m.content,
        m.sent_at,
        partner.user_id AS partner_id,
        partner.user_name AS partner_name,
        GROUP_CONCAT(
            DISTINCT g.goods_name
            ORDER BY g.goods_name
            SEPARATOR ", "
        ) AS goods_names
    FROM messages AS m
    INNER JOIN users AS partner
        ON partner.user_id = CASE
            WHEN m.sender_id = ? THEN m.receiver_id
            ELSE m.sender_id
        END
    INNER JOIN buy AS b
        ON b.transaction_id = m.transaction_id
    INNER JOIN buy_items AS bi
        ON bi.transaction_id = b.transaction_id
    INNER JOIN goods AS g
        ON g.goods_id = bi.goods_id
    WHERE
        (m.sender_id = ? OR m.receiver_id = ?)
        AND (
            (m.sender_id = ? AND m.receiver_id <> ?)
            OR
            (m.receiver_id = ? AND m.sender_id <> ?)
        )
    GROUP BY
        m.transaction_id,
        partner.user_id,
        m.message_id
    ORDER BY
        m.sent_at DESC,
        m.message_id DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $user_id,
    $user_id,
    $user_id,
    $user_id,
    $user_id,
    $user_id,
    $user_id
]);

$all_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 取引IDと会話相手IDごとに最新の1件だけ残す
$latest_conversations = [];

foreach ($all_messages as $message) {
    $conversation_key =
        $message['transaction_id'] . '_' . $message['partner_id'];

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
                <?= htmlspecialchars(
                    $message['partner_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h2>

            <p>
                取引商品：
                <?= htmlspecialchars(
                    $message['goods_names'] ?? '',
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

            <a href="message-list.php?transaction_id=<?= (int) $message['transaction_id'] ?>&amp;partner_id=<?= (int) $message['partner_id'] ?>">
                この会話を開く
            </a>
        </section>

        <hr>

    <?php endforeach; ?>

<?php endif; ?>

<?php require '../../meetory-footer.php'; ?>