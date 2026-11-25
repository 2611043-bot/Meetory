<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) or (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/reports/admin/restriction-form.php');
    header('Location: ../../user/login.php?return_url=' . urlencode($return));
    exit;
}

if (!isset($_SESSION['role']) or $_SESSION['role'] !== 'admin') {
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
if ($target_user_id === false or $target_user_id === null or $target_user_id <= 0) {
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
<style>
/* ページ全体の背景（Meetory標準の淡いブルーグラデーション） */
html:has(.report-page.report-admin),
body:has(.report-page.report-admin) {
    background: linear-gradient(135deg, #e6f1fc 0%, #f4f8fc 52%, #ebf3fb 100%) !important;
    background-attachment: fixed !important;
}

/* メインコンテナ（白のフローティングカード） */
.report-page.report-admin {
    max-width: 920px;
    margin: 24px auto 110px;
    padding: 32px 36px;
    border: 1px solid rgba(25, 118, 210, 0.12);
    border-radius: 20px;
    background-color: rgba(255, 255, 255, 0.96) !important;
    box-shadow: 0 10px 30px -8px rgba(15, 41, 66, 0.08);
    box-sizing: border-box;
    color: #0f172a;
}

/* ページ見出し */
.report-page.report-admin > h1 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 18px;
    font-size: 22px;
    font-weight: 800;
    color: #0f2942;
    letter-spacing: -0.01em;
}

.report-page.report-admin > h1::before {
    content: "";
    display: inline-block;
    width: 5px;
    height: 22px;
    border-radius: 999px;
    background-color: #1976d2;
}

/* フラッシュメッセージ */
.report-page.report-admin .report-flash {
    margin: 0 0 18px;
    padding: 12px 16px;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    background-color: #f0f9ff;
    color: #0369a1;
    font-size: 13px;
    font-weight: 600;
}

/* 上部ナビゲーションリンク（ユーザー一覧に戻る | 履歴を見る） */
.report-page.report-admin > p:has(a[href="users.php"]) {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin: 0 0 24px;
    color: transparent;
    font-size: 0;
}

.report-page.report-admin > p:has(a[href="users.php"]) a {
    display: inline-flex;
    align-items: center;
    padding: 7px 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #f8fbff;
    color: #1976d2;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s;
}

.report-page.report-admin > p:has(a[href="users.php"]) a:hover {
    background-color: #e8f3fe;
    border-color: #90caf9;
}

/* 各セクションカード */
.report-page.report-admin > section {
    margin-bottom: 24px;
    padding: 22px 24px;
    border: 1px solid #e2ebf5;
    border-radius: 16px;
    background-color: #f8fbff;
}

.report-page.report-admin > section:last-of-type {
    margin-bottom: 0;
}

.report-page.report-admin > section h2 {
    margin: 0 0 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dce6f2;
    font-size: 16px;
    font-weight: 800;
    color: #0f2942;
}

.report-page.report-admin > section > p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

/* 現在適用されている制限テーブル */
.report-page.report-admin .report-list {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 12px;
    border: 1px solid #e2ebf5;
    border-radius: 12px;
    overflow: hidden;
    background-color: #ffffff;
}

.report-page.report-admin .report-list thead th {
    padding: 11px 14px;
    border: none;
    border-bottom: 1px solid #dce6f2;
    background-color: #f1f7fe;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.report-page.report-admin .report-list tbody td {
    padding: 12px 14px;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    font-size: 13px;
    vertical-align: middle;
}

.report-page.report-admin .report-list tbody tr:last-child td {
    border-bottom: none;
}

.report-page.report-admin .report-list tbody tr:hover td {
    background-color: #f8fbff;
}

/* 解除ボタン */
.report-page.report-admin .report-list button[name="revoke_restriction_id"] {
    padding: 6px 14px;
    border: 1px solid #fecaca;
    border-radius: 999px;
    background-color: #fef2f2;
    color: #dc2626;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: background-color 0.15s, color 0.15s;
}

.report-page.report-admin .report-list button[name="revoke_restriction_id"]:hover {
    background-color: #dc2626;
    border-color: #dc2626;
    color: #ffffff;
}

.report-page.report-admin .restriction-note {
    margin: 8px 0 0;
    color: #64748b;
    font-size: 12px;
}

/* 新しい制限を設定するフォーム */
.report-page.report-admin .report-form p {
    margin: 0 0 18px;
}

.report-page.report-admin .report-form label {
    display: inline-block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.report-page.report-admin .report-form select,
.report-page.report-admin .report-form textarea,
.report-page.report-admin .report-form input[type="datetime-local"] {
    width: 100%;
    max-width: 480px;
    padding: 10px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    background-color: #ffffff;
    font-size: 14px;
    color: #0f172a;
    font-family: inherit;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.report-page.report-admin .report-form select {
    height: 42px;
    cursor: pointer;
}

.report-page.report-admin .report-form textarea {
    max-width: 100%;
    min-height: 100px;
    line-height: 1.6;
    resize: vertical;
}

.report-page.report-admin .report-form select:focus,
.report-page.report-admin .report-form textarea:focus,
.report-page.report-admin .report-form input[type="datetime-local"]:focus {
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.14);
}

.report-page.report-admin .report-form input[type="datetime-local"]:disabled {
    background-color: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
}

/* 無期限制限チェックボックス行 */
.report-page.report-admin .report-form label:has(#unlimited) {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 0;
    padding: 10px 14px;
    border: 1px solid #dce6f2;
    border-radius: 12px;
    background-color: #ffffff;
    cursor: pointer;
}

.report-page.report-admin .report-form input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #1976d2;
    cursor: pointer;
}

.report-page.report-admin .report-form .hint {
    display: block;
    margin-top: 6px;
    color: #64748b;
    font-size: 12px;
}

/* 送信ボタン（制限を設定する） */
.report-page.report-admin .report-form button[type="submit"] {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 44px;
    padding: 0 28px;
    border: none;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(25, 118, 210, 0.25);
    transition: background-color 0.15s, transform 0.1s;
}

.report-page.report-admin .report-form button[type="submit"]:hover {
    background-color: #1565c0;
}

.report-page.report-admin .report-form button[type="submit"]:active {
    transform: translateY(1px);
}

/* スマホ・タブレット対応 */
@media (max-width: 768px) {
    .report-page.report-admin {
        margin: 12px 10px 100px;
        padding: 20px 16px;
        border-radius: 16px;
    }

    .report-page.report-admin > section {
        padding: 16px 14px;
    }

    .report-page.report-admin .report-list {
        display: block;
        overflow-x: auto;
    }
}
</style>

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