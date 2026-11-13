
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
    exit('ログインしてください。');
}

$my_user_id = (int)$my_user_id;


// ========================================
// トーク一覧をデータベースから取得
// ========================================

$sql = "
    SELECT
        m.message_id,
        partner.user_id,
        partner.user_name,
        m.content,
        m.message_type,
        m.sent_at

    FROM messages AS m

    INNER JOIN users AS partner
        ON partner.user_id = CASE
            WHEN m.sender_id = ?
            THEN m.receiver_id
            ELSE m.sender_id
        END

    WHERE
        (m.sender_id = ? OR m.receiver_id = ?)

        AND NOT EXISTS (

            SELECT 1
            FROM messages AS newer

            WHERE
                (newer.sender_id = ? OR newer.receiver_id = ?)

                AND (
                    CASE
                        WHEN newer.sender_id = ?
                        THEN newer.receiver_id
                        ELSE newer.sender_id
                    END
                ) = (
                    CASE
                        WHEN m.sender_id = ?
                        THEN m.receiver_id
                        ELSE m.sender_id
                    END
                )

                AND (
                    newer.sent_at > m.sent_at

                    OR (
                        newer.sent_at = m.sent_at
                        AND newer.message_id > m.message_id
                    )
                )
        )

    ORDER BY
        m.sent_at DESC,
        m.message_id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id,
    $my_user_id
]);

$talks = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// トーク一覧の表示用データを作成
// ========================================

foreach ($talks as &$talk) {

    // 相手の名前の最初の文字
    $talk['initial'] = mb_substr(
        $talk['user_name'],
        0,
        1,
        'UTF-8'
    );

    // 最後のメッセージ
    if ($talk['message_type'] === 'goods') {

        $talk['last_message'] = '商品を紹介しました。';

    } else {

        $talk['last_message'] = $talk['content'];

    }

    // 送信日時
    $sent_time = strtotime($talk['sent_at']);

    if (date('Y-m-d', $sent_time) === date('Y-m-d')) {

        $talk['time'] = date('H:i', $sent_time);

    } elseif (
        date('Y-m-d', $sent_time) === date('Y-m-d', strtotime('yesterday'))
    ) {

        $talk['time'] = '昨日';

    } else {

        $talk['time'] = date('m/d', $sent_time);

    }

    // 未読件数（未読管理は今後実装）
    $talk['unread'] = 0;
}

unset($talk);


// ========================================
// 共通ヘッダー読み込み
// ========================================

require_once '../../meetory-header.php';
?>

<link rel="stylesheet"
    href="/Meetory/meetory/assets/css/message-center.css">
<link rel="stylesheet"
    href="/Meetory/meetory/assets/css/message-room.css">


<section class="message-center">

    <div class="message-layout">

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

                    <p class="talk-empty">
                        まだトークはありません。
                    </p>

                <?php else: ?>

                    <?php foreach ($talks as $talk): ?>

                        <a
                            href="message-room.php?user_id=<?= (int)$talk['user_id'] ?>"
                            class="talk-item room-talk-item"
                            data-user-id="<?= (int)$talk['user_id'] ?>"
                            data-name="<?= htmlspecialchars(
                                $talk['user_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <div class="talk-avatar">
                                <?= htmlspecialchars(
                                    $talk['initial'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
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
