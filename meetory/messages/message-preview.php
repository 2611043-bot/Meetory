<?php
// Meetory共通ヘッダーを読み込む
require_once '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/message-preview.css">

<main class="chat-container">

    <!-- 通常の会話と商品カードを表示する場所 -->
    <div id="mainMessages"></div>

    <!-- 通常チャットの入力欄 -->
    <form class="input-group footer-input" id="mainForm">

        <input
            id="mainInput"
            type="text"
            maxlength="2000"
            placeholder="メッセージを入力"
            aria-label="通常メッセージを入力"
            autocomplete="off"
        >

        <button type="submit">送信</button>

    </form>

</main>

<script src="../assets/js/message-preview.js"></script>

<?php
// Meetory共通フッターを読み込む
require_once '../../meetory-footer.php';
?>