<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <title>Meetory</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, "Noto Sans JP", sans-serif;
            background: linear-gradient(135deg, #eaf7ff, #cfeeff);
            color: #34495e;
        }

        .welcome-page {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
        }

        .welcome-card {
            width: 100%;
            max-width: 450px;
            padding: 50px 40px;
            background-color: white;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(30, 136, 229, 0.15);
        }

        .logo {
            font-size: 48px;
            font-weight: bold;
            color: #1976d2;
            margin-bottom: 10px;
        }

        .welcome-title {
            font-size: 24px;
            color: #34495e;
            margin-bottom: 10px;
        }

        .welcome-text {
            color: #78909c;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 35px;
        }

        .welcome-button {
            display: block;
            width: 100%;
            padding: 14px;
            margin-bottom: 15px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
        }

        .login-button {
            background-color: #2196f3;
            color: white;
        }

        .register-button {
            background-color: white;
            color: #1976d2;
            border: 2px solid #2196f3;
        }

        .welcome-footer {
            margin-top: 25px;
            color: #90a4ae;
            font-size: 12px;
        }
    </style>
</head>

<body>

<div class="welcome-page">

    <div class="welcome-card">

        <div class="logo">
            Meetory
        </div>

        <h1 class="welcome-title">
            Meetoryへようこそ
        </h1>

        <p class="welcome-text">
            あなたの欲しいもの、<br>
            誰かの欲しいものを見つけよう。
        </p>

        <a href="login.php" class="welcome-button login-button">
            ログイン
        </a>

        <a href="register.php" class="welcome-button register-button">
            新規登録
        </a>

        <div class="welcome-footer">
            Meetory
        </div>

    </div>

</div>

</body>
</html>