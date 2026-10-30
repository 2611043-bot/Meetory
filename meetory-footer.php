<?php
// 現在のページ名を取得
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>

<!-- フッター用CSS -->
<link
    rel="stylesheet"
    href="/meetory_php/meetory/assets/css/footer.css"
>

<footer class="footer-nav">
    <!-- 出品 -->
    <a href="/meetory_php/meetory/goods/goods-register.php">
        <i class="fa-solid fa-plus"></i>
        <span>出品</span>
    </a>

    <!-- 探す -->
    <a href="/meetory_php/meetory/goods/goods-list.php">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>探す</span>
    </a>

    <!-- ホーム -->
    <a href="/meetory_php/meetory/home.php">
        <i class="fa-solid fa-house"></i>
        <span>ホーム</span>
    </a>

    <!-- メッセージ -->
    <a href="/meetory_php/meetory/messages/message-inbox.php">
        <i class="fa-solid fa-envelope"></i>
        <span>メッセージ</span>
    </a>

    <!-- マイページ -->
    <a href="/meetory_php/meetory/user/mypage.php">
        <i class="fa-solid fa-user"></i>
        <span>マイページ</span>
    </a>
</footer>

<!-- フッター用JavaScript -->
<script
    src="/meetory_php/meetory/assets/js/footer.js"
    defer
></script>