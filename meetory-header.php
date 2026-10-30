<?php require_once __DIR__ . '/meetory-functions.php';

// 現在アクセスしているファイル名
$current_page = basename($_SERVER['SCRIPT_NAME']);

// ログイン・会員登録関連の画面か判定
$is_auth_page = in_array(
    $current_page,
    ['login.php', 'register.php', 'password-reset.php'],
    true
);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meetory</title>

    <!-- アイコン用のFont Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <!-- ヘッダー専用CSS -->
    <link
        rel="stylesheet"
        href="/meetory_php/meetory/assets/css/header.css"
    >
</head></head>
<body>

<header class="site-header">
    <div class="header-main <?= $is_auth_page ? 'auth-header' : '' ?>">
        <!-- ロゴ -->
        <a href="/meetory_php/meetory/home.php" class="header-logo">
            <img
                src="/meetory_php/meetory/user/logo.png"
                alt="Meetory"
                class="logo-img"
            >
        </a>

        <?php if (!$is_auth_page): ?>
            <!-- 通常ページのみ：中央の検索バー -->
            <form action="#" method="GET" class="header-search-form">
                <div class="search-input-group">
                    <input type="text" name="keyword" id="searchInput" class="header-search-input" placeholder="Meetoryで商品を検索...">
                    <button type="submit" class="search-submit-btn" title="検索">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
            </form>

            <!-- 通常ページのみ：右側のメニュー -->
            <div class="header-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="#" class="icon-btn" title="お知らせ">
                        <i class="fa-solid fa-bell"></i>
                    </a>
                    <a href="messages/message-inbox.php" class="nav-link">
                        <i class="fa-solid fa-envelope"></i> メッセージ
                    </a>
                    <a href="goods/goods-register.php" class="btn-sell">
                        <i class="fa-solid fa-plus"></i> 出品
                    </a>
                    <a href="user/mypage.php" class="nav-link">マイページ</a>
                    <a href="user/logout.php" class="nav-link">ログアウト</a>
                <?php else: ?>
                    <a href="user/register.php" class="nav-link">会員登録</a>
                    <a href="user/login.php" class="nav-link">ログイン</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</header>

<!-- 検索時に背景を暗くするオーバーレイ -->
<div class="search-overlay" id="searchOverlay"></div>

<!-- ヘッダー専用JavaScript -->
<script
    src="/meetory_php/meetory/assets/js/header.js"
    defer
></script>

<main class="main-container">