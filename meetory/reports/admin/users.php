<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../../meetory-functions.php';

if (empty($_SESSION['user_id']) or (int) $_SESSION['user_id'] <= 0) {
    $return = str_replace('\\', '/', $_SERVER['REQUEST_URI'] ?? '/Meetory/meetory/reports/admin/users.php');
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

// ユーザー検索
$keyword = $_GET['keyword'] ?? '';
if (!is_string($keyword)) {
    $keyword = '';
}
$keyword = trim($keyword);

$users = searchRestrictedUsers($pdo, $keyword);

// Ajax レスポンス：検索結果フラグメントのみ出力（フォーカス維持のためページ全体再読み込みを回避）
if ($_GET['ajax'] ?? '' === '1') {
    header('Content-Type: text/html; charset=utf-8');
    $escape = static function ($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
    $flash = $_SESSION['restriction_flash'] ?? null;
    unset($_SESSION['restriction_flash']);
    ?>
    <?php if ($flash !== null): ?>
        <p class="report-flash" role="status"><?= $escape($flash) ?></p>
    <?php endif; ?>
    <?php if (empty($users)): ?>
        <p>該当するユーザーはありません。</p>
    <?php else: ?>
        <table class="report-list">
            <thead>
                <tr>
                    <th>ユーザーID</th>
                    <th>ユーザー名</th>
                    <th>役割</th>
                    <th>登録日時</th>
                    <th>現在の制限</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $states = [];
                    $raw = $u['restriction_states'] ?? '';
                    if ($raw !== '' and $raw !== null) {
                        foreach (explode(',', $raw) as $pair) {
                            $pair = trim($pair);
                            if ($pair === '') {
                                continue;
                            }
                            list($type, $status) = explode(':', $pair, 2);
                            $states[] = $type;
                        }
                    }
                    $suspended = in_array('suspend', $states, true);
                    ?>
                    <tr>
                        <td><?= (int) $u['user_id'] ?></td>
                        <td>
                            <a href="restriction-form.php?user_id=<?= (int) $u['user_id'] ?>">
                                <?= $escape($u['user_name']) ?>
                            </a>
                        </td>
                        <td><?= $escape($u['role']) ?></td>
                        <td><?= $escape(date('Y/m/d H:i', strtotime($u['created_at']))) ?></td>
                        <td>
                            <?php if (empty($states)): ?>
                                <span class="restriction-none">なし</span>
                            <?php else: ?>
                                <?php foreach ($states as $type): ?>
                                    <span
                                        class="restriction-badge <?= $escape($type) ?>"
                                        title="<?= $escape(getRestrictionTypeLabel($type)) ?>"
                                    >
                                        <?= $escape(getRestrictionTypeLabel($type)) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a
                                href="restriction-form.php?user_id=<?= (int) $u['user_id'] ?>"
                                class="sidebar-link"
                            >
                                制限管理
                            </a>
                            <br>
                            <a
                                href="restriction-history.php?user_id=<?= (int) $u['user_id'] ?>"
                            >
                                履歴
                            </a>
                            <?php if ($suspended): ?>
                                <br>
                                <span style="color:#dc2626;font-size:0.8rem;">停止中</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php
    exit;
}

require '../../../meetory-header.php';
?>

<link rel="stylesheet" href="../../assets/css/reports.css">
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
/* ページ全体の背景（Meetory標準の淡いブルーグラデーション） */
html:has(.report-page.report-admin),
body:has(.report-page.report-admin) {
    background: linear-gradient(135deg, #e6f1fc 0%, #f4f8fc 52%, #ebf3fb 100%) !important;
    background-attachment: fixed !important;
}

.report-page.report-admin {
    background: transparent !important;
    padding: 24px 18px 110px;
}

.report-page.report-admin .admin-layout {
    max-width: 1140px;
    margin: 0 auto;
    gap: 22px;
    align-items: flex-start;
    background: transparent !important;
}

/* 右側メインエリア（白のフローティングカード） */
.report-page.report-admin .admin-main-content {
    flex: 1;
    min-width: 0;
    padding: 28px 32px;
    border: 1px solid rgba(25, 118, 210, 0.12);
    border-radius: 20px;
    background-color: rgba(255, 255, 255, 0.96) !important;
    box-shadow: 0 10px 30px -8px rgba(15, 41, 66, 0.08);
    box-sizing: border-box;
}

/* 見出し */
.report-page.report-admin .admin-main-content h1 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 20px;
    font-size: 22px;
    font-weight: 800;
    color: #0f2942;
    letter-spacing: -0.01em;
}

.report-page.report-admin .admin-main-content h1::before {
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

/* 検索フォーム */
.report-page.report-admin .report-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px !important;
    padding: 14px 18px;
    border: 1px solid #e2ebf5;
    border-radius: 14px;
    background-color: #f8fbff;
}

.report-page.report-admin .report-filter label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.report-page.report-admin .report-filter input[type="text"] {
    flex: 1;
    min-width: 200px;
    max-width: 320px;
    height: 38px;
    padding: 0 14px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background-color: #ffffff;
    font-size: 13px;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.report-page.report-admin .report-filter input[type="text"]:focus {
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.14);
}

.report-page.report-admin .report-filter button[type="submit"] {
    height: 38px;
    padding: 0 20px;
    border: none;
    border-radius: 999px;
    background-color: #1976d2;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(25, 118, 210, 0.22);
    transition: background-color 0.15s;
}

.report-page.report-admin .report-filter button[type="submit"]:hover {
    background-color: #1565c0;
}

/* 検索リセットボタン */
.report-page.report-admin .user-filter-reset-btn {
    height: 38px;
    padding: 0 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #ffffff;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-sizing: border-box;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
    margin-left: 8px;
}

.report-page.report-admin .user-filter-reset-btn:hover {
    background-color: #f1f5f9;
    color: #0f2942;
    border-color: #bfdbfe;
}

/* テーブルデザイン */
.report-page.report-admin .report-list {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 22px;
    border: 1px solid #e2ebf5;
    border-radius: 14px;
    overflow: hidden;
    background-color: #ffffff;
}

.report-page.report-admin .report-list thead th {
    padding: 13px 16px;
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
    padding: 14px 16px;
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

.report-page.report-admin .report-list td a:not(.sidebar-link) {
    color: #1976d2;
    font-weight: 700;
    text-decoration: none;
}

.report-page.report-admin .report-list td a:not(.sidebar-link):hover {
    text-decoration: underline;
}

.report-page.report-admin .report-list td:last-child {
    white-space: nowrap;
}

.report-page.report-admin .report-list td .sidebar-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 14px;
    margin-bottom: 4px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background-color: #eff6ff;
    color: #1565c0;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, color 0.15s;
}

.report-page.report-admin .report-list td .sidebar-link:hover {
    background-color: #1976d2;
    border-color: #1976d2;
    color: #ffffff;
}

.report-page.report-admin .report-list td:last-child a:not(.sidebar-link) {
    display: inline-block;
    margin-top: 2px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}

.report-page.report-admin .report-list td:last-child a:not(.sidebar-link):hover {
    color: #1976d2;
}

.restriction-badge {
    display: inline-block;
    padding: 3px 10px;
    margin: 2px 4px 2px 0;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}
.restriction-badge.suspend { background: #dc2626; }
.restriction-badge.listing { background: #ca8a04; }
.restriction-badge.purchase { background: #0ea5e9; }
.restriction-badge.message { background: #8b5cf6; }
.restriction-none {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    background-color: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.report-page.report-admin .admin-main-content > p:last-child {
    margin: 0;
}

.report-page.report-admin .admin-main-content > p:last-child a {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border: 1px solid #dce6f2;
    border-radius: 999px;
    background-color: #f8fbff;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: background-color 0.15s, color 0.15s;
}

.report-page.report-admin .admin-main-content > p:last-child a:hover {
    background-color: #e8f3fe;
    color: #1976d2;
}

@media (max-width: 768px) {
    .report-page.report-admin .admin-main-content {
        padding: 20px 16px;
        border-radius: 16px;
    }

    .report-page.report-admin .report-list {
        display: block;
        overflow-x: auto;
    }
}
</style>

<main class="report-page report-admin">
    <div class="admin-layout">
        <?php require __DIR__ . '/../../admin/includes/admin-sidebar.php'; ?>
        <div class="admin-main-content">

    <h1>ユーザー制限管理</h1>

    <form id="user-filter-form" action="users.php" method="get" class="report-filter" style="margin-bottom: 1rem;">
        <label for="keyword">ユーザー名で検索</label>
        <input
            type="text"
            id="keyword"
            name="keyword"
            value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="ユーザー名を入力"
        >
        <button type="button" id="user-filter-reset" class="user-filter-reset-btn">検索リセット</button>
    </form>

    <div id="user-results">
    <?php if ($flash !== null): ?>
        <p class="report-flash" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if (empty($users)): ?>
        <p>該当するユーザーはありません。</p>
    <?php else: ?>
        <table class="report-list">
            <thead>
                <tr>
                    <th>ユーザーID</th>
                    <th>ユーザー名</th>
                    <th>役割</th>
                    <th>登録日時</th>
                    <th>現在の制限</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $states = [];
                    $raw = $u['restriction_states'] ?? '';
                    if ($raw !== '' and $raw !== null) {
                        foreach (explode(',', $raw) as $pair) {
                            $pair = trim($pair);
                            if ($pair === '') {
                                continue;
                            }
                            list($type, $status) = explode(':', $pair, 2);
                            $states[] = $type;
                        }
                    }
                    $suspended = in_array('suspend', $states, true);
                    ?>
                    <tr>
                        <td><?= (int) $u['user_id'] ?></td>
                        <td>
                            <a href="restriction-form.php?user_id=<?= (int) $u['user_id'] ?>">
                                <?= htmlspecialchars($u['user_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(date('Y/m/d H:i', strtotime($u['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if (empty($states)): ?>
                                <span class="restriction-none">なし</span>
                            <?php else: ?>
                                <?php foreach ($states as $type): ?>
                                    <span
                                        class="restriction-badge <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                                        title="<?= htmlspecialchars(getRestrictionTypeLabel($type), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <?= htmlspecialchars(getRestrictionTypeLabel($type), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a
                                href="restriction-form.php?user_id=<?= (int) $u['user_id'] ?>"
                                class="sidebar-link"
                            >
                                制限管理
                            </a>
                            <br>
                            <a
                                href="restriction-history.php?user_id=<?= (int) $u['user_id'] ?>"
                            >
                                履歴
                            </a>
                            <?php if ($suspended): ?>
                                <br>
                                <span style="color:#dc2626;font-size:0.8rem;">停止中</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>

    <p>
        <a href="index.php">通報管理に戻る</a>
    </p>

        </div>
    </div>
</main>

<script>
(function () {
    'use strict';

    var form = document.getElementById('user-filter-form');
    var keywordInput = document.getElementById('keyword');
    var resetBtn = document.getElementById('user-filter-reset');
    var resultsContainer = document.getElementById('user-results');
    var timer = null;
    var controller = null;

    if (!form || !keywordInput || !resultsContainer) {
        return;
    }

    function buildQueryString() {
        var params = new URLSearchParams();
        if (keywordInput.value !== '') {
            params.append('keyword', keywordInput.value);
        }
        params.append('ajax', '1');
        return params.toString();
    }

    function fetchResults() {
        if (controller) {
            controller.abort();
        }
        controller = new AbortController();

        var url = form.action + '?' + buildQueryString();

        fetch(url, {
            method: 'GET',
            signal: controller.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('ネットワークエラーが発生しました。');
            }
            return response.text();
        })
        .then(function(html) {
            resultsContainer.innerHTML = html;
        })
        .catch(function(err) {
            if (err.name !== 'AbortError') {
                console.error(err);
                resultsContainer.innerHTML = '<p>検索結果の取得に失敗しました。</p>';
            }
        });
    }

    function debounceFetch() {
        if (timer !== null) {
            clearTimeout(timer);
        }
        timer = setTimeout(function() {
            timer = null;
            fetchResults();
        }, 400);
    }

    keywordInput.addEventListener('input', function () {
        debounceFetch();
    });

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        if (controller) {
            controller.abort();
            controller = null;
        }
        fetchResults();
    });

    resetBtn.addEventListener('click', function() {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        if (controller) {
            controller.abort();
            controller = null;
        }
        keywordInput.value = '';
        fetchResults();
    });
})();
</script>

<?php require '../../../meetory-footer.php'; ?>