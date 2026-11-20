<?php
// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// CSRFトークンを生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// エラーと入力保持値を取得
$errors = $_SESSION['register_errors'] ?? [];
$old = $_SESSION['register_old'] ?? [];

// 一度表示したらクリア
unset($_SESSION['register_errors'], $_SESSION['register_old']);

$old_user_name = htmlspecialchars($old['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
$old_email = htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8');

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/login.css">

<div class="login-page">
    <div class="login-card">

        <h1 class="login-title">新規登録</h1>

        <p class="login-description">
            Meetoryのアカウントを作成します
        </p>

        <?php if (!empty($errors['common'])): ?>
            <p class="error-message">
                <?= htmlspecialchars($errors['common'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form action="register-process.php" method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="form-group">
                <label for="user_name">ユーザー名（2〜10文字・空白不可）</label>
                <input
                    type="text"
                    id="user_name"
                    name="user_name"
                    placeholder="ユーザー名を入力"
                    required
                    value="<?= $old_user_name ?>"
                    class="<?= isset($errors['user_name']) ? 'input-error' : '' ?>"
                >
                <?php if (isset($errors['user_name'])): ?>
                    <p class="error-message" style="text-align:left; margin-top:6px;">
                        <?= htmlspecialchars($errors['user_name'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="example@meetory.jp"
                    required
                    value="<?= $old_email ?>"
                    class="<?= isset($errors['email']) ? 'input-error' : '' ?>"
                >
                <?php if (isset($errors['email'])): ?>
                    <p class="error-message" style="text-align:left; margin-top:6px;">
                        <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">パスワード（英数字を含む8〜16文字）</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="パスワードを入力"
                        required
                        class="<?= isset($errors['password']) ? 'input-error' : '' ?>"
                    >
                    <button type="button" id="togglePasswordBtn" class="toggle-password-btn" aria-label="パスワードの表示切り替え">
                        <i class="fa-solid fa-eye-slash" id="eyeIcon"></i>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <p class="error-message" style="text-align:left; margin-top:6px;">
                        <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirm">パスワード（確認）</label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    placeholder="もう一度パスワードを入力"
                    required
                    class="<?= isset($errors['password_confirm']) ? 'input-error' : '' ?>"
                >
                <?php if (isset($errors['password_confirm'])): ?>
                    <p class="error-message" style="text-align:left; margin-top:6px;">
                        <?= htmlspecialchars($errors['password_confirm'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <input
                type="submit"
                value="アカウントを登録する"
                class="login-button"
            >
        </form>

        <p class="register-link">
            すでにアカウントをお持ちの方
            <a href="login.php">ログイン</a>
        </p>

    </div>
</div>

<script src="../assets/js/login.js"></script>
<?php require '../../meetory-footer.php'; ?>