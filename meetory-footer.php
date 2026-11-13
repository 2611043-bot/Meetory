<?php
// 現在のページ名を取得
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>

<!-- フッター用CSS -->
<link
    rel="stylesheet"
    href="/Meetory/meetory/assets/css/footer.css"
>

<footer class="footer-nav">
    <!-- 出品 -->
    <a href="/Meetory/meetory/goods/goods-register.php">
        <i class="fa-solid fa-plus"></i>
        <span>出品</span>
    </a>

    <!-- 探す -->
    <a href="/Meetory/meetory/goods/goods-list.php">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>探す</span>
    </a>

    <!-- ホーム -->
    <a href="/Meetory/meetory/index.php">
        <i class="fa-solid fa-house"></i>
        <span>ホーム</span>
    </a>

    <!-- メッセージ -->
    <a href="/Meetory/meetory/messages/message-center.php"
    class="footer-item">
        <span class="footer-icon">
            <i class="fa-solid fa-envelope"></i>
        </span>
        <span class="footer-label">メッセージ</span>
    </a>

    <!-- マイページ -->
    <a href="/Meetory/meetory/user/mypage.php">
        <i class="fa-solid fa-user"></i>
        <span>マイページ</span>
    </a>
</footer>

<!-- フッター用JavaScript -->
<script
    src="/Meetory/meetory/assets/js/footer.js"
    defer
></script>