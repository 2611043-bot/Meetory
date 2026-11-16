<?php

// セッションが開始されていなければ開始する
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// CSRFトークンを生成（全ページで利用可能にするため）
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 共通関数を読み込む
require_once __DIR__ . '/meetory-functions.php';

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
        href="/Meetory/meetory/assets/css/header.css?v=<?= filemtime(__DIR__ . '/meetory/assets/css/header.css') ?>"
    >
</head>

<body>

<header class="site-header">
    <div class="header-main <?= $is_auth_page ? 'auth-header' : '' ?>">

        <!-- ロゴ -->
        <a href="/Meetory/meetory/index.php" class="header-logo">
            <img
                src="/Meetory/meetory/user/logo.png"
                alt="Meetory"
                class="logo-img"
            >
        </a>

        <?php if (!$is_auth_page): ?>

            <!-- 検索バー -->
            <form
                action="/Meetory/meetory/goods/goods-list.php"
                method="GET"
                class="header-search-form"
            >
                <div class="search-input-group">
                    <input
                        type="text"
                        name="keyword"
                        id="searchInput"
                        class="header-search-input"
                        placeholder="Meetoryで商品を検索..."
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="searchSuggestions"
                        aria-expanded="false"
                    >

                    <button
                        type="submit"
                        class="search-submit-btn"
                        title="検索"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
                <div
                    class="search-suggestions"
                    id="searchSuggestions"
                    role="region"
                    aria-label="検索候補"
                    hidden
                ></div>
            </form>

            <!-- 右側のメニュー -->
            <div class="header-actions">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <!-- ハンバーガーメニューボタン -->
                    <button
                        type="button"
                        class="icon-btn"
                        id="hamburgerBtn"
                        aria-label="メニューを開く"
                    >
                        <i class="fa-solid fa-bars"></i>
                    </button>

                <?php else: ?>

                    <a
                        href="/Meetory/meetory/user/register.php"
                        class="nav-link"
                    >
                        会員登録
                    </a>

                    <a
                        href="/Meetory/meetory/user/login.php"
                        class="nav-link"
                    >
                        ログイン
                    </a>

                    <!-- ハンバーガーメニューボタン（未ログイン時も表示） -->
                    <button
                        type="button"
                        class="icon-btn"
                        id="hamburgerBtn"
                        aria-label="メニューを開く"
                    >
                        <i class="fa-solid fa-bars"></i>
                    </button>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

    <!-- サイドメニューオーバーレイ（header-mainの外に配置） -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- サイドメニュー（header-mainの外に配置） -->
    <div class="sidebar-menu" id="sidebarMenu">
        <button
            type="button"
            class="sidebar-close"
            id="sidebarClose"
            aria-label="メニューを閉じる"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>
        <nav class="sidebar-nav">
            <!-- ショッピング -->
            <h2 class="sidebar-section-title">ショッピング</h2>
            <a
                href="/Meetory/meetory/index.php"
                class="sidebar-link"
            >
                <i class="fa-solid fa-house"></i>
                ホーム
            </a>
                    <a
                        href="/Meetory/meetory/goods/goods-list.php"
                        class="sidebar-link"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        商品一覧
                    </a>
                    <a
                        href="/Meetory/meetory/user/favorites.php"
                        class="sidebar-link"
                    >
                        <i class="fa-solid fa-heart"></i>
                        ほしいものリスト
                    </a>

            <!-- アカウント -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <h2 class="sidebar-section-title">アカウント</h2>
            <a
                href="/Meetory/meetory/user/mypage.php"
                class="sidebar-link"
            >
                <i class="fa-solid fa-history"></i>
                購入履歴
            </a>
            <a
                href="/Meetory/meetory/goods/my-goods.php"
                class="sidebar-link"
            >
                <i class="fa-solid fa-box"></i>
                出品履歴
            </a>
                    <a
                        href="/Meetory/meetory/user/profile.php?user_id=<?= (int) $_SESSION['user_id'] ?>"
                        class="sidebar-link"
                    >
                        <i class="fa-solid fa-user-gear"></i>
                        プロフィール・設定
                    </a>
            <?php endif; ?>

            <!-- その他 -->
            <h2 class="sidebar-section-title">その他</h2>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a
                href="/Meetory/meetory/user/logout.php"
                class="sidebar-link sidebar-logout"
            >
                <i class="fa-solid fa-sign-out-alt"></i>
                ログアウト
            </a>
            <a
                href="/Meetory/meetory/user/account-delete.php"
                class="sidebar-link sidebar-delete"
                data-delete-account
            >
                <i class="fa-solid fa-user-minus"></i>
                アカウント削除
            </a>
            <?php else: ?>
            <a
                href="/Meetory/meetory/user/login.php"
                class="sidebar-link sidebar-logout"
            >
                <i class="fa-solid fa-sign-in-alt"></i>
                ログイン
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- アカウント削除確認モーダル -->
    <div class="delete-account-modal-overlay" id="deleteAccountModalOverlay"></div>
    <div class="delete-account-modal" id="deleteAccountModal">
        <div class="delete-account-modal-content">
            <h2 class="delete-account-modal-title">アカウントを削除しますか？</h2>
            <p class="delete-account-modal-warning">
                削除するとアカウントを元に戻せなくなります。
            </p>
            <div class="delete-account-modal-actions">
                <button
                    type="button"
                    class="delete-account-modal-btn delete-account-modal-cancel"
                    id="deleteAccountCancel"
                >
                    いいえ
                </button>
                <button
                    type="button"
                    class="delete-account-modal-btn delete-account-modal-confirm"
                    id="deleteAccountConfirm"
                >
                    はい
                </button>
            </div>
        </div>
    </div>

</header>

<!-- 検索時のオーバーレイ -->
<div class="search-overlay" id="searchOverlay"></div>

    <!-- ヘッダー専用JavaScript -->
    <script
        src="/Meetory/meetory/assets/js/header.js?v=2026110901"
        defer
    ></script>
    <script
        src="/Meetory/meetory/assets/js/search-suggest.js?v=<?= filemtime(__DIR__ . '/meetory/assets/js/search-suggest.js') ?>"
        defer
    ></script>

    <!-- CSRFトークン（全ページ共通、アカウント削除等で使用） -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

<main class="main-container">