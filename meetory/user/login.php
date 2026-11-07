<?php require '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/login.css">

<div class="login-page">

    <div class="login-card">

        <h1 class="login-title">ログイン</h1>

        <p class="login-description">
            Meetoryへようこそ
        </p>

        <form action="login-process.php" method="post">

            <?php $return_url = $_GET['return_url'] ?? ''; ?>
            <?php if ($return_url !== ''): ?>
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="user_name">ユーザー名</label>
                <input
                    type="text"
                    id="user_name"
                    name="user_name"
                    placeholder="ユーザー名を入力"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="パスワードを入力"
                        required
                    >
                    <button type="button" id="togglePasswordBtn" class="toggle-password-btn" aria-label="パスワードの表示切り替え">
                        <i class="fa-solid fa-eye-slash" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <input
                type="submit"
                value="ログイン"
                class="login-button"
            >

        </form>

        <!-- パスワード再設定リンク -->
        <p class="register-link">
            <a href="password-reset.php">パスワードをお忘れの方はこちら</a>
        </p>

        <!-- 新規登録リンク -->
        <p class="register-link">
            アカウントをお持ちでない方
            <a href="register.php">新規登録</a>
        </p>

    </div>

</div>

<script src="../assets/js/login.js"></script>