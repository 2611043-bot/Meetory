<?php
require_once '../../meetory-header.php';

// 表示確認用のサンプルデータ
// あとからMySQLから取得する形に変更する
$talks = [
    [
        'user_id' => 2,
        'user_name' => 'ユーザーA',
        'last_message' => '商品の発送はいつ頃になりますか？',
        'time' => '14:30',
        'unread' => 2,
        'initial' => 'A'
    ],
    [
        'user_id' => 3,
        'user_name' => 'ユーザーB',
        'last_message' => 'ありがとうございます！',
        'time' => '昨日',
        'unread' => 0,
        'initial' => 'B'
    ],
    [
        'user_id' => 4,
        'user_name' => 'ユーザーC',
        'last_message' => '購入を検討しています。',
        'time' => '10/20',
        'unread' => 0,
        'initial' => 'C'
    ]
];
?>

<link rel="stylesheet"
    href="/Meetory/meetory/assets/css/message-center.css">

<section class="message-center">

    <div class="message-heading">
        <h1>メッセージ</h1>
        <p>ユーザーとのやり取りを確認できます。</p>
    </div>

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
                    placeholder="トークを検索..."
                    autocomplete="off"
                >
            </div>

            <div class="talk-list" id="talkList">

                <?php foreach ($talks as $talk): ?>

                    <a
                        href="message-room.php?user_id=<?= (int)$talk['user_id'] ?>"
                        class="talk-item"
                        data-name="<?= htmlspecialchars($talk['user_name'], ENT_QUOTES, 'UTF-8') ?>"
                    >

                        <div class="talk-avatar">
                            <?= htmlspecialchars($talk['initial'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                        <div class="talk-info">

                            <div class="talk-name-row">
                                <span class="talk-user-name">
                                    <?= htmlspecialchars($talk['user_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <span class="talk-time">
                                    <?= htmlspecialchars($talk['time'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <div class="talk-message-row">
                                <p class="talk-last-message">
                                    <?= htmlspecialchars($talk['last_message'], ENT_QUOTES, 'UTF-8') ?>
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

                <p class="talk-empty" id="talkEmpty" hidden>
                    該当するトークがありません。
                </p>

            </div>

        </aside>

        <!-- 右側：トーク未選択時の表示 -->
        <section class="talk-welcome">

            <div class="welcome-icon">
                <i class="fa-regular fa-comments"></i>
            </div>

            <h2>メッセージ</h2>

            <p>
                左側のトーク一覧から相手を選択すると、
                ここに会話が表示されます。
            </p>

        </section>

    </div>

</section>

<script src="/Meetory/meetory/assets/js/message-center.js" defer></script>

</main>

<?php require_once '../../Meetory/meetory-footer.php'; ?>