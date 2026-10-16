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

<form action="login-process.php" method="post">
ユーザー名
<input type="text" name="user_name" required>
パスワード
<input type="password" name="password" required>
<input type="submit" value="ログイン">
<p>
<a href="password-reset.php">パスワードを忘れた場合</a>
</p>
<p>
<a href="register.php">新規登録はこちら</a>
</p>
</form>
<?php require '../../meetory-footer.php'; ?>
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
    margin-top: 25px;
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
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="パスワードを入力"
                    required
                >
            </div>

            <input
                type="submit"
                value="ログイン"
                class="login-button"
            >

        </form>

        <p class="register-link">
    アカウントをお持ちでない方
    <a href="register.php">新規登録</a>
</p>
    </div>

</div>

<?php require '../../meetory-footer.php'; ?>
```
