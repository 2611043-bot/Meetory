<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/reports/admin/restriction-form.php');
    header('Location: ../../user/login.php?return_url=' . urlencode($return));
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit('このページを閲覧する権限がありません。');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = getPDO();

// フラッシュメッセージ
$flash = $_SESSION['restriction_flash'] ?? null;
unset($_SESSION['restriction_flash']);

// 対象ユーザー
$target_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if ($target_user_id === false || $target_user_id === null || $target_user_id <= 0) {
    exit('ユーザーIDが正しくありません。');
}

$stmt = $pdo->prepare('SELECT user_id, user_name, role FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target_user) {
    exit('ユーザーが見つかりません。');
}

$admin_id = (int) $_SESSION['user_id'];

// 現在有効な制限
$restrictions = getActiveRestrictions($pdo, $target_user_id);

require '../../../meetory-header.php';
?>

<link rel="stylesheet" href="../../assets/css/reports.css">

<main class="report-page report-admin">

    <h1>制限管理：<?= htmlspecialchars($target_user['user_name'], ENT_QUOTES, 'UTF-8') ?></h1>

    <?php if ($flash !== null): ?>
        <p class="report-flash" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p>
        <a href="users.php">ユーザー一覧に戻る</a>
        |
        <a href="restriction-history.php?user_id=<?= (int) $target_user_id ?>">履歴を見る</a>
    </p>

    <section>
        <h2>現在適用されている制限</h2>

        <?php if (empty($restrictions)): ?>
            <p>現在、このユーザーに適用されている制限はありません。</p>
        <?php else: ?>
            <form action="restriction-process.php" method="post" class="restriction-revoke-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="revoke">
                <input type="hidden" name="user_id" value="<?= (int) $target_user_id ?>">
                <table class="report-list">
                    <thead>
                        <tr>
                            <th>種類</th>
                            <th>理由</th>
                            <th>開始日時</th>
                            <th>終了日時</th>
                            <th>設定管理者</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($restrictions as $r): ?>
                            <?php
                            $adminName = '(不明)';
                            if (!empty($r['created_by'])) {
                                $aStmt = $pdo->prepare('SELECT user_name FROM users WHERE user_id = ? LIMIT 1');
                                $aStmt->execute([(int) $r['created_by']]);
                                $aRow = $aStmt->fetch(PDO::FETCH_ASSOC);
                                if ($aRow) {
                                    $adminName = htmlspecialchars($aRow['user_name'], ENT_QUOTES, 'UTF-8');
                                }
                            }
                            $startsAt = date('Y/m/d H:i', strtotime($r['starts_at']));
                            $endsAt = $r['ends_at'] !== null
                                ? date('Y/m/d H:i', strtotime($r['ends_at']))
                                : '無期限';
                            ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(getRestrictionTypeLabel($r['restriction_type']), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8')) ?></td>
                                <td><?= htmlspecialchars($startsAt, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($endsAt, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $adminName ?></td>
                                <td>
                                    <input type="hidden" name="restriction_id[]" value="<?= (int) $r['restriction_id'] ?>">
                                    <button type="submit" name="revoke_restriction_id" value="<?= (int) $r['restriction_id'] ?>" onclick="return confirm('この制限だけを解除しますか？他の制限には影響しません。');">
                                        解除
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="restriction-note">
                    ※ 「解除」を押すと、その行の制限だけが解除されます。他の制限には影響しません。
                </p>
            </form>
        <?php endif; ?>
    </section>

    <section>
        <h2>新しい制限を設定する</h2>

        <form action="restriction-process.php" method="post" class="report-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="user_id" value="<?= (int) $target_user_id ?>">

            <p>
                <label for="restriction_type">制限の種類</label><br>
                <select id="restriction_type" name="restriction_type" required>
                    <option value="suspend">アカウント停止</option>
                    <option value="listing">出品制限</option>
                    <option value="purchase">購入制限</option>
                    <option value="message">メッセージ送信制限</option>
                </select>
            </p>

            <p>
                <label for="reason">制限理由（必須）</label><br>
                <textarea id="reason" name="reason" required rows="4" placeholder="制限の理由を入力してください"></textarea>
            </p>

            <p>
                <label for="starts_at">制限開始日時</label><br>
                <input type="datetime-local" id="starts_at" name="starts_at" required>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="unlimited" id="unlimited" value="1">
                    無期限制限にする（終了日時を設定しない）
                </label>
            </p>

            <p>
                <label for="ends_at">制限終了日時（任意）</label><br>
                <input type="datetime-local" id="ends_at" name="ends_at">
                <span class="hint">終了日時を過ぎると自動的に解除されます。</span>
            </p>

            <button type="submit">制限を設定する</button>
        </form>
    </section>

</main>

<?php require '../../../meetory-footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var unlimited = document.getElementById('unlimited');
    var endsAt = document.getElementById('ends_at');
    if (!unlimited || !endsAt) {
        return;
    }
    function toggleEndsAt() {
        endsAt.disabled = unlimited.checked;
        if (unlimited.checked) {
            endsAt.value = '';
        }
    }
    unlimited.addEventListener('change', toggleEndsAt);
    toggleEndsAt();
});
</script>
