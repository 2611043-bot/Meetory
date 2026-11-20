<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログインしているか確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

// 共通処理を読み込む
require_once '../../meetory-functions.php';

$pdo = getPDO();
$user_id = (int) $_SESSION['user_id'];

// ユーザー情報を取得
$stmt = $pdo->prepare('SELECT user_name, email, role FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// 現在有効な制限と履歴を取得
$restrictions = getActiveRestrictions($pdo, $user_id);
$history = getRestrictionHistory($pdo, $user_id);

// CSRFトークン
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require '../../meetory-header.php';
?>

<link rel="stylesheet" href="../assets/css/account-status.css">

<main class="account-status-page">

    <h1>アカウント状態の確認</h1>

    <p>
        <a href="mypage.php">マイページへ戻る</a>
    </p>

    <section class="account-profile">
        <h2>ユーザー情報</h2>
        <dl>
            <dt>ユーザー名</dt>
            <dd><?= htmlspecialchars($user['user_name'], ENT_QUOTES, 'UTF-8') ?></dd>
            <dt>メールアドレス</dt>
            <dd><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></dd>
        </dl>
    </section>

    <section class="account-restrictions">
        <h2>現在の停止・制限状態</h2>

        <?php if (empty($restrictions)): ?>
            <p>現在、停止や利用制限は適用されていません。</p>
        <?php else: ?>
            <table class="restriction-list">
                <thead>
                    <tr>
                        <th>種類</th>
                        <th>理由</th>
                        <th>開始日時</th>
                        <th>終了日時</th>
                        <th>設定管理者</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($restrictions as $r): ?>
                        <?php
                        $adminName = '(不明)';
                        if (!empty($r['created_by'])) {
                            $adminStmt = $pdo->prepare('SELECT user_name FROM users WHERE user_id = ? LIMIT 1');
                            $adminStmt->execute([(int) $r['created_by']]);
                            $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
                            if ($adminRow) {
                                $adminName = htmlspecialchars($adminRow['user_name'], ENT_QUOTES, 'UTF-8');
                            }
                        }
                        $startsAt = date('Y/m/d H:i', strtotime($r['starts_at']));
                        $endsAt = $r['ends_at'] !== null
                            ? date('Y/m/d H:i', strtotime($r['ends_at']) . ' まで')
                            : '無期限';
                        ?>
                        <tr>
                            <td>
                                <span class="restriction-badge restriction-<?= htmlspecialchars($r['restriction_type'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars(getRestrictionTypeLabel($r['restriction_type']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8')) ?></td>
                            <td><?= htmlspecialchars($startsAt, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($endsAt, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $adminName ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="restriction-note">
                ※ 終了日時が設定されている制限は、期限を過ぎると自動的に解除されます。
                無期限の制限は管理者が解除するまで継続します。
            </p>
        <?php endif; ?>
    </section>

    <section class="account-history">
        <h2>停止・制限履歴</h2>

        <?php if (empty($history)): ?>
            <p>履歴はありません。</p>
        <?php else: ?>
            <table class="history-list">
                <thead>
                    <tr>
                        <th>日時</th>
                        <th>操作</th>
                        <th>種類</th>
                        <th>理由</th>
                        <th>管理者</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <?php
                        $adminName = '(不明)';
                        if (!empty($h['admin_id'])) {
                            $adminStmt = $pdo->prepare('SELECT user_name FROM users WHERE user_id = ? LIMIT 1');
                            $adminStmt->execute([(int) $h['admin_id']]);
                            $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
                            if ($adminRow) {
                                $adminName = htmlspecialchars($adminRow['user_name'], ENT_QUOTES, 'UTF-8');
                            }
                        }
                        $actionLabels = [
                            'created' => '設定',
                            'changed' => '変更',
                            'revoked' => '解除',
                            'expired' => '期限切れ',
                        ];
                        $actionLabel = $actionLabels[$h['action']] ?? $h['action'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(date('Y/m/d H:i', strtotime($h['logged_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(getRestrictionTypeLabel($h['restriction_type']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $h['reason'] !== null ? nl2br(htmlspecialchars($h['reason'], ENT_QUOTES, 'UTF-8')) : '－' ?></td>
                            <td><?= $adminName ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</main>

<?php require '../../meetory-footer.php'; ?>
