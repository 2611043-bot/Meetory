<?php require '../../meetory-header.php'; ?>

<link rel="stylesheet" href="../assets/css/password-reset.css">

<div class="password-reset-page">

    <div class="password-reset-card">

        <h1 class="password-reset-title">パスワードリセット</h1>

        <p class="password-reset-description">
            登録したメールアドレスを入力してください。
        </p>

        <form action="password-reset-process.php" method="post">

            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="メールアドレスを入力"
                    required
                >
            </div>

            <input
                type="submit"
                value="リセットURLを送信"
                class="reset-button"
            >

        </form>

        <p class="back-link">
            <a href="login.php">ログイン画面に戻る</a>
        </p>

    </div>

</div>