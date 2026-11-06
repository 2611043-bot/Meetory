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

<!-- パスワード表示切替＆強度判定用JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- 1. パスワード表示・非表示の切り替え処理 ---
    const toggleBtns = document.querySelectorAll('.toggle-password-btn');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const eyeIcon = this.querySelector('i');

            if (targetInput && eyeIcon) {
                const isPassword = targetInput.getAttribute('type') === 'password';
                targetInput.setAttribute('type', isPassword ? 'text' : 'password');

                if (isPassword) {
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                } else {
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                }
            }
        });
    });

    // --- 2. パスワード強度判定処理 ---
    const passwordInput = document.getElementById('password');
    const wrapper = document.getElementById('strength-wrapper');
    const bar = document.getElementById('strength-bar');
    const text = document.getElementById('strength-text');

    if (!passwordInput || !wrapper || !bar || !text) return;

    passwordInput.addEventListener('input', function() {
        const password = this.value;

        // 未入力時は非表示
        if (password.length === 0) {
            wrapper.style.display = 'none';
            bar.style.width = '0%';
            text.textContent = '';
            return;
        }

        wrapper.style.display = 'block';

        let score = 0;

        // 1. 文字数による基本スコア
        if (password.length >= 1) score += 1;
        if (password.length >= 6) score += 1;
        if (password.length >= 8) score += 1;

        // 2. 文字種の多様性チェック
        if (/[0-9]/.test(password) && /[a-zA-Z]/.test(password)) score += 1; // 英数混在
        if (/[A-Z]/.test(password) || /[^a-zA-Z0-9]/.test(password)) score += 1; // 大文字または記号

        // 3. 安全性（危険パターンの減点チェック）
        const lowerPass = password.toLowerCase();
        const weakPatterns = ['1234', 'password', 'qwerty', 'abc', '0000', '1111', 'admin'];
        const isWeakWord = weakPatterns.some(pattern => lowerPass.includes(pattern));
        const hasRepeatingChar = /(.)\1\1/.test(password); // 3回以上の同じ文字の連続

        if (isWeakWord) score -= 2;
        if (hasRepeatingChar) score -= 1;

        // スコアの範囲制限 (0〜5)
        score = Math.max(0, score);

        // 4. 強度表示（強・中・弱）
        if (score <= 2) {
            bar.style.width = '33%';
            bar.style.backgroundColor = '#f44336'; // 赤
            text.textContent = '強度: 弱';
            text.style.color = '#f44336';
        } else if (score <= 3) {
            bar.style.width = '66%';
            bar.style.backgroundColor = '#ff9800'; // オレンジ
            text.textContent = '強度: 中';
            text.style.color = '#ff9800';
        } else {
            bar.style.width = '100%';
            bar.style.backgroundColor = '#4caf50'; // 緑
            text.textContent = '強度: 強';
            text.style.color = '#4caf50';
        }
    });
});
</script>

<?php require '../../meetory-footer.php'; ?>