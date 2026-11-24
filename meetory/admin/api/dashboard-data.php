<?php

// セッションを開始（admin-auth.php と整合させるため）
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// JSONレスポンスを返す
header('Content-Type: application/json; charset=utf-8');

// 未ログインチェック
if (
    empty($_SESSION['user_id']) ||
    (int) $_SESSION['user_id'] <= 0
) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'ログインが必要です。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 管理者権限チェック
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => '管理者権限が必要です。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 管理者認証を共通化
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getPDO();

$response = [
    'success' => false,
    'stats' => [
        'total_users' => 0,
        'total_goods' => 0,
        'total_transactions' => 0,
        'completed_transactions' => 0
    ],
    'charts' => [
        'user_registrations' => [],
        'goods_listings' => []
    ],
    'activities' => [],
    'updated_at' => date('Y-m-d H:i:s')
];

try {
    // 統計情報取得（index.php から移植）
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
    $stmt->execute();
    $response['stats']['total_users'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM goods');
    $stmt->execute();
    $response['stats']['total_goods'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM buy');
    $stmt->execute();
    $response['stats']['total_transactions'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM buy WHERE transaction_status = :status');
    $stmt->bindValue(':status', '取引完了', PDO::PARAM_STR);
    $stmt->execute();
    $response['stats']['completed_transactions'] = (int) $stmt->fetchColumn();

    // チャートデータ取得（dashboard.php から移植）
    $startDate = date('Y-m-01', strtotime('-5 months'));

    $userData = [];
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
        FROM users
        WHERE created_at >= :start_date
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->execute();
    $userResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($userResults as $row) {
        $userData[] = [
            'month' => $row['month'],
            'count' => (int) $row['cnt']
        ];
    }
    $response['charts']['user_registrations'] = $userData;

    $goodsData = [];
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(listed_at, '%Y-%m') AS month, COUNT(*) AS cnt
        FROM goods
        WHERE listed_at >= :start_date
        GROUP BY DATE_FORMAT(listed_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->execute();
    $goodsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($goodsResults as $row) {
        $goodsData[] = [
            'month' => $row['month'],
            'count' => (int) $row['cnt']
        ];
    }
    $response['charts']['goods_listings'] = $goodsData;

    // 最近の活動履歴取得（dashboard.php から移植）
    $activities = [];

    // 1. ユーザー登録
    $stmt = $pdo->prepare("
        SELECT u.created_at AS activity_time,
               'registration' AS activity_type,
               'ユーザー登録' AS activity_label,
               CONCAT('ユーザー「', u.user_name, '」が登録されました') AS activity_content,
               u.user_name AS activity_user
        FROM users AS u
        ORDER BY u.created_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }

    // 2. 商品出品
    $stmt = $pdo->prepare("
        SELECT g.listed_at AS activity_time,
               'listing' AS activity_type,
               '商品出品' AS activity_label,
               CONCAT('商品「', g.goods_name, '」が出品されました') AS activity_content,
               u.user_name AS activity_user
        FROM goods AS g
        JOIN users AS u ON g.seller_id = u.user_id
        ORDER BY g.listed_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }

    // 3. 購入・取引開始
    $stmt = $pdo->prepare("
        SELECT b.purchased_at AS activity_time,
               'purchase' AS activity_type,
               '購入' AS activity_label,
               CONCAT('商品「', SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT g.goods_name ORDER BY g.goods_name SEPARATOR '、'), ',', 50), '」を含む取引を購入しました') AS activity_content,
               bu.user_name AS activity_user
        FROM buy AS b
        JOIN buy_items AS bi ON bi.transaction_id = b.transaction_id
        JOIN goods AS g ON g.goods_id = bi.goods_id
        JOIN users AS bu ON b.buyer_id = bu.user_id
        GROUP BY b.transaction_id, b.purchased_at, bu.user_name
        ORDER BY b.purchased_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }

    // 4. 取引発送
    $stmt = $pdo->prepare("
        SELECT d.shipped_at AS activity_time,
               'shipping' AS activity_type,
               '発送' AS activity_label,
               CONCAT('取引ID ', d.transaction_id, ' を発送しました') AS activity_content,
               GROUP_CONCAT(DISTINCT u.user_name ORDER BY u.user_name SEPARATOR '、') AS activity_user
        FROM deliveries AS d
        JOIN users AS u ON d.seller_id = u.user_id
        GROUP BY d.transaction_id, d.shipped_at
        ORDER BY d.shipped_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }

    // 5. 取引完了
    $stmt = $pdo->prepare("
        SELECT b.completed_at AS activity_time,
               'completion' AS activity_type,
               '取引完了' AS activity_label,
               CONCAT('取引ID ', b.transaction_id, ' の取引が完了しました') AS activity_content,
               bu.user_name AS activity_user
        FROM buy AS b
        JOIN users AS bu ON b.buyer_id = bu.user_id
        WHERE b.transaction_status = '取引完了'
        ORDER BY b.completed_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }

    // 6. アカウント制限の作成・解除
    $stmt = $pdo->prepare("
        SELECT al.logged_at AS activity_time,
               CASE al.action
                   WHEN 'created' THEN 'restriction_created'
                   ELSE 'restriction_revoked'
               END AS activity_type,
               CASE al.action
                   WHEN 'created' THEN '制限設定'
                   ELSE '制限解除'
               END AS activity_label,
               CONCAT('ユーザー「', u.user_name, '」へ',
                      CASE al.action
                          WHEN 'created' THEN '制限を設定'
                          ELSE '制限を解除'
                      END,
                      '（', al.restriction_type, '）') AS activity_content,
               u.user_name AS activity_user
        FROM account_restriction_logs AS al
        JOIN users AS u ON al.user_id = u.user_id
        ORDER BY al.logged_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = $row;
    }

    // 日時を基準に新しい順に並べ替え（NULLの場合は0として扱う）
    usort($activities, function ($a, $b) {
        $timeA = $a['activity_time'] ? strtotime($a['activity_time']) : 0;
        $timeB = $b['activity_time'] ? strtotime($b['activity_time']) : 0;
        return $timeB <=> $timeA;
    });

    // 最新15件だけを取得
    $activities = array_slice($activities, 0, 15);
    $response['activities'] = $activities;

    $response['success'] = true;

} catch (Throwable $e) {
    error_log('admin dashboard API error: ' . $e->getMessage());
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'データの取得に失敗しました。'
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
