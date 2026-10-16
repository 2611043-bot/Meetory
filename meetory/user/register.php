<?php 
session_start();
require '../../meetory-header.php';

// セッションからエラー情報と入力データを取得
$errors = $_SESSION['register_errors'] ?? [];
$old = $_SESSION['register_old'] ?? [];

// 一度表示したらセッションをクリア（リロード時の再表示防止）
unset($_SESSION['register_errors'], $_SESSION['register_old']);
?>

<style>
    .register-page {
        min-height: 70vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 60px 20px;
        background: linear-gradient(135deg, #eef8ff, #f8fbff);
    }

    .register-card {
        width: 100%;
        max-width: 420px;
        background-color: #ffffff;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(30, 136, 229, 0.12);
        border: 1px solid #e1f0fa;
    }

    .register-title {
        text-align: center;
        margin-bottom: 10px;
        font-size: 32px;
        color: #1976d2;
    }

    .register-description {
        text-align: center;
        margin-bottom: 30px;
        color: #6b7c8f;
        font-size: 14px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #34495e;
    }

    .form-group input {
        width: 100%;
        box-sizing: border-box;
        padding: 13px 15px;
        border: 1px solid #cddfea;
        border-radius: 8px;
        font-size: 16px;
        outline: none;
        transition: 0.2s;
    }

    .form-group input:focus {
        border-color: #2196f3;
        box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.12);
    }

    /* エラー発生時のスタイル */
    .form-group input.is-invalid {
        border-color: #e53935;
        background-color: #fff8f8;
    }

    .field-error {
        color: #e53935;
        font-size: 13px;
        margin-top: 6px;
        font-weight: bold;
    }

    /* パスワード強度表示用のコンパクトスタイル */
    .strength-wrapper {
        margin-top: 6px;
        display: none; /* 初期状態では非表示 */
    }

    .strength-bar-bg {
        width: 100%;
        height: 3px;
        background-color: #e0e0e0;
        border-radius: 2px;
        overflow: hidden;
    }

    .strength-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s ease, background-color 0.3s ease;
    }

    .strength-text {
        font-size: 11px;
        margin-top: 3px;
        font-weight: bold;
        color: #6b7c8f;
    }

    .register-button {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 8px;
        background: linear-gradient(135deg, #2196f3, #1976d2);
        color: white;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s;
    }

    .register-button:hover {
        background: linear-gradient(135deg, #1976d2, #1565c0);
    }

    .login-link {
        text-align: center;
        margin-top: 25px;
        font-size: 14px;
        color: #6b7c8f;
    }

    .login-link a {
        color: #1976d2;
        text-decoration: underline;
        text-underline-offset: 4px;
        font-weight: bold;
    }

    .login-link a:hover {
        color: #1565c0;
    }
</style>

<div class="register-page">

    <div class="register-card">

        <h1 class="register-title">会員登録</h1>

        <p class="register-description">
            Meetoryをはじめよう
        </p>

        <form action="register-process.php" method="post">

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
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                    placeholder="パスワードを入力"
                    required
                >
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
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    class="<?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                    placeholder="もう一度パスワードを入力"
                    required
                >
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

<!-- パスワード強度判定用JavaScript（安全性チェック＋文字数） -->
<script>
document.addEventListener('DOMContentLoaded', function() {
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
        const hasRepeatingChar = /(.)\1\1/.test(password); // 3回以上の同じ文字の連続（例: aaa, 111）

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