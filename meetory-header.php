<?php require_once 'meetory-functions.php'; 

// 現在アクセスしているファイル名を取得してログイン関連画面か判定
$current_page = basename($_SERVER['SCRIPT_NAME']);
$is_auth_page = in_array($current_page, ['login.php', 'register.php', 'password-reset.php']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meetory</title>
    <!-- アイコン用のFontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f4f7fb;
            color: #333;
        }

        /* ヘッダー全体 */
        .site-header {
            background: #ffffff;
            border-bottom: 2px solid #e1e8ed;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* メインヘッダーエリア */
        .header-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        /* ログイン・登録画面ではロゴを中央寄せにする */
        .header-main.auth-header {
            justify-content: center;
        }

        /* ロゴ */
        .header-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .header-logo .logo-img {
            height: 42px;
            width: auto;
            display: block;
        }

        /* 大きな検索バー領域 */
        .header-search-form {
            flex: 1;
            display: flex;
            align-items: center;
            margin: 0 10px;
        }

        .search-input-group {
            width: 100%;
            display: flex;
            align-items: center;
            border: 1px solid #d0d7de;
            border-radius: 24px;
            overflow: hidden;
            background-color: #f6f8fa;
            transition: all 0.2s ease;
        }

        .search-input-group:focus-within {
            border-color: #1a73e8;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
        }

        /* 検索入力欄 */
        .header-search-input {
            flex: 1;
            border: none;
            outline: none;
            padding: 10px 16px;
            font-size: 15px;
            color: #333;
            background: transparent;
        }

        /* 検索ボタン */
        .search-submit-btn {
            background-color: #1a73e8;
            border: none;
            color: #ffffff;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }

        .search-submit-btn:hover {
            background-color: #0d47a1;
        }

        /* 右側メニュー */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        /* アイコンボタン（共通） */
        .icon-btn {
            color: #444;
            font-size: 18px;
            background: none;
            border: none;
            padding: 8px 10px;
            border-radius: 50%;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s, color 0.2s;
        }

        .icon-btn:hover {
            background-color: #f0f4f9;
            color: #1a73e8;
        }

        /* 出品ボタン */
        .btn-sell {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: #ffffff !important;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(26, 115, 232, 0.3);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-sell:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.4);
        }

        /* グレー枠付きナビゲーションボタン（マイページ・ログアウト等） */
        .nav-link {
            color: #444;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 7px 16px;
            border-radius: 20px;
            border: 1px solid #d0d7de;
            background-color: transparent;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .nav-link:hover {
            background-color: #f0f4f9;
            border-color: #1a73e8;
            color: #1a73e8;
        }

        /* 画面を暗くするオーバーレイ */
        .search-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .search-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* メインコンテンツ囲み */
        .main-container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 20px;
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="header-main <?= $is_auth_page ? 'auth-header' : '' ?>">
        <!-- ロゴ -->
        <a href="../user/login-home.php" class="header-logo">
            <img src="../user/logo.png" alt="Meetory" class="logo-img">
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
                    <a href="../messages/message-inbox.php" class="nav-link">
                        <i class="fa-solid fa-envelope"></i> メッセージ
                    </a>
                    <a href="../goods/goods-register.php" class="btn-sell">
                        <i class="fa-solid fa-plus"></i> 出品
                    </a>
                    <a href="../user/mypage.php" class="nav-link">マイページ</a>
                    <a href="../user/logout.php" class="nav-link">ログアウト</a>
                <?php else: ?>
                    <a href="../user/register.php" class="nav-link">会員登録</a>
                    <a href="../user/login.php" class="nav-link">ログイン</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</header>

<!-- 検索時に背景を暗くする要素 -->
<div class="search-overlay" id="searchOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const searchOverlay = document.getElementById('searchOverlay');

    if (searchInput && searchOverlay) {
        searchInput.addEventListener('focus', () => {
            searchOverlay.classList.add('active');
        });

        searchInput.addEventListener('blur', () => {
            searchOverlay.classList.remove('active');
        });
    }
});
</script>

<main class="main-container">