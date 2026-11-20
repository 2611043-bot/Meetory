<?php

// 管理者認証チェック（直接アクセス防止）
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['user_id']) ||
    (int) $_SESSION['user_id'] <= 0 ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    return;
}

// 現在のURLを取得（アクティブメニュー判定用）
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

$isDashboard = strpos($currentUri, '/admin/index.php') !== false || strpos($currentUri, '/admin/dashboard.php') !== false;
$isReports   = strpos($currentUri, '/reports/admin/index.php') !== false;
$isUsers     = strpos($currentUri, '/reports/admin/users.php') !== false;
$isInquiries = strpos($currentUri, '/reports/admin/inquiries.php') !== false;
?>

<aside class="admin-sidebar">
    <nav class="admin-sidebar-nav">
        <h2 class="admin-sidebar-title">管理メニュー</h2>
        <ul class="admin-sidebar-list">
            <li>
                <a
                    href="/Meetory/meetory/admin/index.php"
                    class="admin-sidebar-link<?= $isDashboard ? ' active' : '' ?>"
                >
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>ダッシュボード</span>
                </a>
            </li>
            <li>
                <a
                    href="/Meetory/meetory/reports/admin/index.php"
                    class="admin-sidebar-link<?= $isReports ? ' active' : '' ?>"
                >
                    <i class="fa-solid fa-flag"></i>
                    <span>通報管理</span>
                </a>
            </li>
            <li>
                <a
                    href="/Meetory/meetory/reports/admin/users.php"
                    class="admin-sidebar-link<?= $isUsers ? ' active' : '' ?>"
                >
                    <i class="fa-solid fa-user-shield"></i>
                    <span>ユーザー制限管理</span>
                </a>
            </li>
            <li>
                <a
                    href="/Meetory/meetory/reports/admin/inquiries.php"
                    class="admin-sidebar-link<?= $isInquiries ? ' active' : '' ?>"
                >
                    <i class="fa-solid fa-envelope"></i>
                    <span>お問い合わせ管理</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
