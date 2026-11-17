<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: ../user/login.php');
    exit;
}

$transaction_id = filter_input(INPUT_GET, 'transaction_id', FILTER_VALIDATE_INT);

require_once '../../meetory-header.php';
?>
<link rel="stylesheet" href="../assets/css/transaction-detail.css">

<main>
    <h1>取引をキャンセルしました</h1>
    <div class="cancel-complete-box">
        <p>取引のキャンセルが完了しました。対象の商品は再び購入可能になりました。</p>
        <?php if ($transaction_id !== false && $transaction_id !== null && $transaction_id > 0): ?>
            <p>
                <a href="transaction-detail.php?transaction_id=<?= (int) $transaction_id ?>">取引詳細を見る</a>
            </p>
        <?php endif; ?>
        <p><a href="../user/mypage.php">マイページへ戻る</a></p>
    </div>
</main>

<?php require_once '../../meetory-footer.php'; ?>
