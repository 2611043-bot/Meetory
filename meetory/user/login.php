<?php require '../../meetory-header.php'; ?>

<style>
.login-page {
    min-height: 70vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, #eef8ff, #f8fbff);
}

.login-card {
    width: 100%;
    max-width: 420px;
    background-color: #ffffff;
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(30, 136, 229, 0.12);
    border: 1px solid #e1f0fa;
}

.login-title {
    text-align: center;
    margin-bottom: 10px;
    font-size: 32px;
    color: #1976d2;
}

.login-description {
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

/* パスワード切り替え用スタイル追加 */
.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper input {
    padding-right: 45px; /* アイコンがかぶらないよう右余白を調整 */
}

.toggle-password-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #888;
    cursor: pointer;
    padding: 6px;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.toggle-password-btn:hover {
    color: #1976d2;
}

.login-button {
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

.login-button:hover {
    background: linear-gradient(135deg, #1976d2, #1565c0);
}

.register-link {
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
    color: #6b7c8f;
}

.register-link a {
    color: #1976d2;
    text-decoration: underline;
    text-underline-offset: 4px;
    font-weight: bold;
}

.register-link a:hover {
    color: #1565c0;
}
</style>

<div class="login-page">

    <div class="login-card">

        <h1 class="login-title">ログイン</h1>

        <p class="login-description">
            Meetoryへようこそ
        </p>

        <form action="login-process.php" method="post">

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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const eyeIcon = document.getElementById('eyeIcon');

    if (togglePasswordBtn && passwordInput && eyeIcon) {
        togglePasswordBtn.addEventListener('click', () => {
            // パスワードの入力タイプ切り替え
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            
            // アイコンの見た目を切り替え (斜め線あり ⇄ 目のみ)
            if (isPassword) {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
    }
});
</script>

<?php require '../../meetory-footer.php'; ?>