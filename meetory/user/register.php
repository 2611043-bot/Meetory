<?php
session_start();
// CSRFトークンを作成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require '../../meetory-header.php';

// セッションからエラー情報と入力データを取得
$errors = $_SESSION['register_errors'] ?? [];
$old = $_SESSION['register_old'] ?? [];

// 一度表示したらセッションをクリア（リロード時の再表示防止）
unset($_SESSION['register_errors'], $_SESSION['register_old']);
?>

<link rel="stylesheet" href="../assets/css/register.css">

<div class="register-page">

    <div class="register-card">

        <h1 class="register-title">会員登録</h1>

        <p class="register-description">
            Meetoryをはじめよう
        </p>

        <form action="register-process.php" method="post">
            <input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
>

            <!-- ユーザー名 -->
            <div class="form-group">
                <label for="user_name">ユーザー名</label>
                <input
                    type="text"
                    id="user_name"
                    name="user_name"
                    class="<?= isset($errors['user_name']) ? 'is-invalid' : '' ?>"
                    value="<?= htmlspecialchars($old['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="ユーザー名を入力"
                    required
                >
                <?php if (isset($errors['user_name'])): ?>
                    <p class="field-error"><?= htmlspecialchars($errors['user_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>

            <!-- メールアドレス -->
            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                    value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="例) meetory@example.com"
                    required
                >
                <?php if (isset($errors['email'])): ?>
                    <p class="field-error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>

            <!-- パスワード -->
            <div class="form-group">
                <label for="password">パスワード</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                        placeholder="パスワードを入力"
                        required
                    >
                    <button type="button" class="toggle-password-btn" data-target="password" aria-label="パスワードの表示切り替え">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <p class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <!-- パスワード強度表示バー -->
                <div id="strength-wrapper" class="strength-wrapper">
                    <div class="strength-bar-bg">
                        <div id="strength-bar" class="strength-bar"></div>
                    </div>
                    <div id="strength-text" class="strength-text"></div>
                </div>
            </div>

            <!-- パスワード（確認） -->
            <div class="form-group">
                <label for="password_confirm">パスワード（確認）</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        class="<?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                        placeholder="もう一度パスワードを入力"
                        required
                    >
                    <button type="button" class="toggle-password-btn" data-target="password_confirm" aria-label="パスワードの表示切り替え">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
                <?php if (isset($errors['password_confirm'])): ?>
                    <p class="field-error"><?= htmlspecialchars($errors['password_confirm'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>

            <input
                type="submit"
                value="登録"
                class="register-button"
            >

        </form>

        <p class="login-link">
            すでにアカウントをお持ちの方
            <a href="login.php">ログインはこちら</a>
        </p>

    </div>

</div>

<script src="../assets/js/register.js"></script>

<?php require '../../meetory-footer.php'; ?>