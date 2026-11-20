
<?php
// ========================================
// 初期設定
// ========================================

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 共通ファイル読み込み
require_once '../../meetory-functions.php';

$pdo = getPDO();


// ========================================
// ログイン確認
// ========================================

$my_user_id = $_SESSION['user_id'] ?? null;

if (!$my_user_id) {
    header('Location: ../user/login.php');
    exit;
}

$my_user_id = (int)$my_user_id;


// ========================================
// 会話ごとの最新メッセージを取得
// ========================================

$sql = "
    SELECT
        m.message_id,
        m.transaction_id,
        m.goods_id,
        m.content,
        m.message_type,
        m.sent_at,
        g.goods_name,
        partner.user_id AS partner_id,
        partner.user_name,
        partner.profile_image_type,
        partner.profile_image
    FROM messages AS m
    INNER JOIN users AS partner
        ON partner.user_id = CASE
            WHEN m.sender_id = :sender_user_id THEN m.receiver_id
            ELSE m.sender_id
        END
    LEFT JOIN goods AS g
        ON g.goods_id = m.goods_id
    WHERE
        (m.sender_id = :filter_sender_id OR m.receiver_id = :filter_receiver_id)
        AND m.sender_id <> m.receiver_id
    ORDER BY m.sent_at DESC, m.message_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':sender_user_id' => $my_user_id,
    ':filter_sender_id' => $my_user_id,
    ':filter_receiver_id' => $my_user_id,
]);

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$latestTalks = [];

foreach ($messages as $message) {
    $partnerId = (int) $message['partner_id'];

    if ($message['transaction_id'] !== null) {
        $contextType = 'transaction';
        $contextId = (int) $message['transaction_id'];
        $conversationKey = 'transaction_' . $contextId . '_partner_' . $partnerId;
        $contextLabel = '取引 #' . $contextId;
    } elseif (
        $message['goods_id'] !== null &&
        $message['message_type'] !== 'goods'
    ) {
        $contextType = 'goods';
        $contextId = (int) $message['goods_id'];
        $conversationKey = 'goods_' . $contextId . '_partner_' . $partnerId;
        $contextLabel = $message['goods_name'] ?? '商品へのDM';
    } else {
        $contextType = 'direct';
        $contextId = null;
        $conversationKey = 'direct_partner_' . $partnerId;
        $contextLabel = 'メッセージ';
    }

    if (isset($latestTalks[$conversationKey])) {
        continue;
    }

    $message['context_type'] = $contextType;
    $message['context_id'] = $contextId;
    $message['context_label'] = $contextLabel;
    $message['last_message'] = $message['message_type'] === 'goods'
        ? '商品を紹介しました。'
        : $message['content'];

    $sentTime = strtotime($message['sent_at']);
    if (date('Y-m-d', $sentTime) === date('Y-m-d')) {
        $message['time'] = date('H:i', $sentTime);
    } elseif (date('Y-m-d', $sentTime) === date('Y-m-d', strtotime('yesterday'))) {
        $message['time'] = '昨日';
    } else {
        $message['time'] = date('m/d', $sentTime);
    }

    $message['unread'] = 0;
    $latestTalks[$conversationKey] = $message;
}

$talks = array_values($latestTalks);


// ========================================
// 共通ヘッダー読み込み
// ========================================

// メッセージページ用のbodyクラスを設定
$body_class = 'message-page';

require_once '../../meetory-header.php';
?>

<link rel="stylesheet"
    href="/Meetory/meetory/assets/css/message-center.css?v=<?= filemtime(__DIR__ . '/../assets/css/message-center.css') ?>">
<link rel="stylesheet"
    href="/Meetory/meetory/assets/css/message-room.css?v=<?= filemtime(__DIR__ . '/../assets/css/message-room.css') ?>">


<section class="message-center">

    <div class="message-layout" id="messageLayout">

        <!-- 左側：トーク一覧 -->
        <aside class="talk-sidebar">

            <div class="talk-sidebar-header">
                <h2>トーク一覧</h2>
            </div>

            <div class="talk-search">
                <input
                    type="search"
                    id="talkSearch"
                    placeholder="トーク相手を検索..."
                    autocomplete="off"
                >
            </div>

            <div class="talk-list" id="talkList">

                <?php if (empty($talks)): ?>

                <p class="talk-empty" id="noTalks">
                        まだトークはありません。
                    </p>

                <?php else: ?>

                    <?php foreach ($talks as $talk): ?>

                        <?php
                        $partnerId = (int) $talk['partner_id'];
                        if ($talk['context_type'] === 'transaction') {
                            $talkUrl = 'message-center.php?transaction_id='
                                . (int) $talk['context_id']
                                . '&partner_id=' . $partnerId;
                        } elseif ($talk['context_type'] === 'goods') {
                            $talkUrl = 'message-center.php?goods_id='
                                . (int) $talk['context_id']
                                . '&partner_id=' . $partnerId;
                        } else {
                            $talkUrl = 'message-center.php?user_id=' . $partnerId;
                        }
                        ?>

                        <a
                            href="<?= htmlspecialchars($talkUrl, ENT_QUOTES, 'UTF-8') ?>"
                            class="talk-item"
                            data-conversation-type="<?= htmlspecialchars($talk['context_type'], ENT_QUOTES, 'UTF-8') ?>"
                            data-context-id="<?= (int) ($talk['context_id'] ?? 0) ?>"
                            data-partner-id="<?= $partnerId ?>"
                            data-name="<?= htmlspecialchars($talk['user_name'] . ' ' . $talk['context_label'], ENT_QUOTES, 'UTF-8') ?>"
                        >

                            <div class="talk-avatar">
                                <?= renderProfileAvatar($talk, '', '') ?>
                            </div>

                            <div class="talk-info">

                                <div class="talk-name-row">

                                    <span class="talk-user-name">
                                        <?= htmlspecialchars(
                                            $talk['user_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                    <span class="talk-time">
                                        <?= htmlspecialchars(
                                            $talk['time'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                </div>

                                <span class="talk-context">
                                    <?= htmlspecialchars(
                                        $talk['context_label'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <div class="talk-message-row">

                                    <p class="talk-last-message">
                                        <?= htmlspecialchars(
                                            $talk['last_message'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                    <?php if ($talk['unread'] > 0): ?>

                                        <span class="unread-badge">
                                            <?= (int)$talk['unread'] ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </a>

                    <?php endforeach; ?>

                    <?php endif; ?>

                <p class="talk-empty" id="talkEmpty" hidden>
                    該当するトークがありません。
                </p>

            </div>

        </aside>


        <!-- 右側：会話表示エリア -->
        <section id="messageRoomPanel">

            <div class="talk-welcome">

                <div class="welcome-icon">
                    <i class="fa-regular fa-comments"></i>
                </div>

                <h2>メッセージ</h2>

                <p>
                    左側のトーク一覧から相手を選択すると、
                    ここに会話が表示されます。
                </p>

            </div>

        </section>

    </div>

</section>


<script
    src="/Meetory/meetory/assets/js/message-center.js?v=<?= filemtime(__DIR__ . '/../assets/js/message-center.js') ?>"
    defer
></script>


</main>

<?php require_once '../../meetory-footer.php'; ?>
