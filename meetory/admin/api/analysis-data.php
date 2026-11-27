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

// キャンセル済みステータス（既存コードと整合）
$cancelledStatus = 'キャンセル済み';
$completedStatus = '取引完了';

// 直近6か月（当月を含む）の月ラベルを生成（古い月から新しい月へ）
$monthLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $monthLabels[] = date('Y-m', strtotime("-$i months"));
}

// 集計期間の開始日（5か月前の月初）
$startDate = date('Y-m-01', strtotime('-5 months'));

// すべて0で初期化（データがない月も0として表示）
$purchaseData = [];
$completedData = [];
$salesData = [];
foreach ($monthLabels as $month) {
    $purchaseData[] = ['month' => $month, 'count' => 0];
    $completedData[] = ['month' => $month, 'count' => 0];
    $salesData[] = ['month' => $month, 'amount' => 0];
}

try {
    // 月別購入件数（購入日時 basd、キャンセル済み除外）
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(purchased_at, '%Y-%m') AS month, COUNT(*) AS cnt
        FROM buy
        WHERE purchased_at >= :start_date
          AND transaction_status != :cancelled_status
        GROUP BY DATE_FORMAT(purchased_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':cancelled_status', $cancelledStatus, PDO::PARAM_STR);
    $stmt->execute();
    $purchaseResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($purchaseResults as $row) {
        $month = $row['month'];
        $index = array_search($month, $monthLabels, true);
        if ($index !== false) {
            $purchaseData[$index]['count'] = (int) $row['cnt'];
        }
    }

    // 月別取引完了件数（完了日時 basd、取引完了のみ、completed_at NOT NULL）
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(completed_at, '%Y-%m') AS month, COUNT(*) AS cnt
        FROM buy
        WHERE completed_at IS NOT NULL
          AND transaction_status = :completed_status
          AND completed_at >= :start_date
        GROUP BY DATE_FORMAT(completed_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':completed_status', $completedStatus, PDO::PARAM_STR);
    $stmt->execute();
    $completedResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($completedResults as $row) {
        $month = $row['month'];
        $index = array_search($month, $monthLabels, true);
        if ($index !== false) {
            $completedData[$index]['count'] = (int) $row['cnt'];
        }
    }

    // 月別売上金額（購入日時 basd、キャンセル済み除外、total_price SUM）
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(purchased_at, '%Y-%m') AS month, SUM(total_price) AS total_amount
        FROM buy
        WHERE purchased_at >= :start_date
          AND transaction_status != :cancelled_status
        GROUP BY DATE_FORMAT(purchased_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':cancelled_status', $cancelledStatus, PDO::PARAM_STR);
    $stmt->execute();
    $salesResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($salesResults as $row) {
        $month = $row['month'];
        $index = array_search($month, $monthLabels, true);
        if ($index !== false) {
            $salesData[$index]['amount'] = (int) $row['total_amount'];
        }
    }

    // カテゴリー別出品数（未売却商品、カテゴリー名付き）
    $categoryListingData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT c.category_name, COUNT(g.goods_id) AS cnt
            FROM goods AS g
            LEFT JOIN categories AS c ON g.category_id = c.category_id
            WHERE g.is_sold = 0
            GROUP BY c.category_id, c.category_name
            ORDER BY cnt DESC
        ");
        $stmt->execute();
        $categoryListingResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($categoryListingResults as $row) {
            $categoryListingData[] = [
                'label' => $row['category_name'] ?: 'カテゴリー未設定',
                'count' => (int) $row['cnt']
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api category listing error: ' . $e->getMessage());
    }

    // 商品の販売状況（販売中 / 出品停止 / 売却済み）
    $salesStatusData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN g.is_sold = 1 THEN 1 ELSE 0 END) AS sold_count,
                SUM(CASE WHEN g.is_sold = 0 AND g.is_active = 1 THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN g.is_sold = 0 AND g.is_active = 0 THEN 1 ELSE 0 END) AS inactive_count
            FROM goods AS g
        ");
        $stmt->execute();
        $statusRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($statusRow) {
            $salesStatusData = [
                ['label' => '販売中', 'count' => (int) ($statusRow['active_count'] ?? 0)],
                ['label' => '出品停止', 'count' => (int) ($statusRow['inactive_count'] ?? 0)],
                ['label' => '売却済み', 'count' => (int) ($statusRow['sold_count'] ?? 0)]
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api sales status error: ' . $e->getMessage());
    }

    // 商品別売上ランキング TOP10（キャンセル済み除外）
    $productRankingData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT g.goods_id, g.goods_name, c.category_name,
                   COUNT(bi.buy_item_id) AS purchase_count,
                   SUM(bi.purchase_price) AS total_sales
            FROM buy_items AS bi
            INNER JOIN buy AS b ON bi.transaction_id = b.transaction_id
            INNER JOIN goods AS g ON bi.goods_id = g.goods_id
            LEFT JOIN categories AS c ON g.category_id = c.category_id
            WHERE b.transaction_status IN ('購入済み', '発送済み', '取引完了')
              AND b.cancelled_at IS NULL
            GROUP BY g.goods_id, g.goods_name, c.category_name
            ORDER BY total_sales DESC
            LIMIT 10
        ");
        $stmt->execute();
        $rankingResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rankingResults as $row) {
            $productRankingData[] = [
                'goods_id' => (int) $row['goods_id'],
                'goods_name' => $row['goods_name'] ?: '商品名取得不可',
                'category_name' => $row['category_name'] ?: '-',
                'purchase_count' => (int) $row['purchase_count'],
                'total_sales' => (int) $row['total_sales']
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api product ranking error: ' . $e->getMessage());
    }

    // カテゴリー別売上金額（キャンセル済み除外）
    $categorySalesData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT c.category_name, SUM(bi.purchase_price) AS total_amount
            FROM buy_items AS bi
            INNER JOIN buy AS b ON bi.transaction_id = b.transaction_id
            INNER JOIN goods AS g ON bi.goods_id = g.goods_id
            LEFT JOIN categories AS c ON g.category_id = c.category_id
            WHERE b.transaction_status IN ('購入済み', '発送済み', '取引完了')
              AND b.cancelled_at IS NULL
            GROUP BY c.category_id, c.category_name
            ORDER BY total_amount DESC
        ");
        $stmt->execute();
        $categorySalesResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($categorySalesResults as $row) {
            $categorySalesData[] = [
                'label' => $row['category_name'] ?: 'カテゴリー未設定',
                'amount' => (int) $row['total_amount']
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api category sales error: ' . $e->getMessage());
    }

    // ユーザー別購入回数ランキング TOP10（一般ユーザーのみ、キャンセル済み除外）
    $userPurchaseRankingData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.user_name, COUNT(b.transaction_id) AS purchase_count
            FROM users AS u
            LEFT JOIN buy AS b ON b.buyer_id = u.user_id
               AND b.transaction_status IN ('購入済み', '発送済み', '取引完了')
               AND b.cancelled_at IS NULL
            WHERE u.role = 'user'
            GROUP BY u.user_id, u.user_name
            ORDER BY purchase_count DESC, u.user_id ASC
            LIMIT 10
        ");
        $stmt->execute();
        $purchaseRankingResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($purchaseRankingResults as $row) {
            $userPurchaseRankingData[] = [
                'user_id' => (int) $row['user_id'],
                'user_name' => $row['user_name'] ?: 'ユーザー名取得不可',
                'purchase_count' => (int) $row['purchase_count']
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api user purchase ranking error: ' . $e->getMessage());
    }

    // ユーザー別出品数ランキング TOP10（一般ユーザーのみ）
    $userListingRankingData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.user_name,
                   SUM(CASE WHEN g.is_sold = 1 THEN 1 ELSE 0 END) AS sold_count,
                   SUM(CASE WHEN g.is_sold = 0 AND g.is_active = 1 THEN 1 ELSE 0 END) AS active_count,
                   SUM(CASE WHEN g.is_sold = 0 AND g.is_active = 0 THEN 1 ELSE 0 END) AS inactive_count
            FROM users AS u
            LEFT JOIN goods AS g ON g.seller_id = u.user_id
            WHERE u.role = 'user'
            GROUP BY u.user_id, u.user_name
            ORDER BY (
                SUM(CASE WHEN g.is_sold = 1 THEN 1 ELSE 0 END)
                + SUM(CASE WHEN g.is_sold = 0 AND g.is_active = 1 THEN 1 ELSE 0 END)
                + SUM(CASE WHEN g.is_sold = 0 AND g.is_active = 0 THEN 1 ELSE 0 END)
            ) DESC, u.user_id ASC
            LIMIT 10
        ");
        $stmt->execute();
        $listingRankingResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($listingRankingResults as $row) {
            $totalListing = (int) ($row['active_count'] ?? 0) + (int) ($row['inactive_count'] ?? 0) + (int) ($row['sold_count'] ?? 0);
            $userListingRankingData[] = [
                'user_id' => (int) $row['user_id'],
                'user_name' => $row['user_name'] ?: 'ユーザー名取得不可',
                'listing_count' => $totalListing,
                'active_count' => (int) ($row['active_count'] ?? 0),
                'inactive_count' => (int) ($row['inactive_count'] ?? 0),
                'sold_count' => (int) ($row['sold_count'] ?? 0)
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api user listing ranking error: ' . $e->getMessage());
    }

    // ユーザー別平均評価ランキング TOP10（全ユーザー、レビュー件数1件以上）
    $userRatingRankingData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.user_name,
                   COUNT(r.review_id) AS review_count,
                   AVG(r.rating) AS avg_rating
            FROM users AS u
            INNER JOIN reviews AS r ON r.reviewee_id = u.user_id
            WHERE u.role = 'user'
            GROUP BY u.user_id, u.user_name
            HAVING review_count > 0
            ORDER BY avg_rating DESC, u.user_id ASC
            LIMIT 10
        ");
        $stmt->execute();
        $ratingRankingResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ratingRankingResults as $row) {
            $userRatingRankingData[] = [
                'user_id' => (int) $row['user_id'],
                'user_name' => $row['user_name'] ?: 'ユーザー名取得不可',
                'review_count' => (int) $row['review_count'],
                'avg_rating' => round((float) $row['avg_rating'], 2)
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api user rating ranking error: ' . $e->getMessage());
    }

    // 月別ユーザー登録推移（一般ユーザーのみ、直近6か月）
    $userRegistrationData = [];
    foreach ($monthLabels as $month) {
        $userRegistrationData[] = ['month' => $month, 'count' => 0];
    }
    try {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
            FROM users
            WHERE created_at >= :start_date
              AND role = 'user'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        $registrationResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($registrationResults as $row) {
            $month = $row['month'];
            $index = array_search($month, $monthLabels, true);
            if ($index !== false) {
                $userRegistrationData[$index]['count'] = (int) $row['cnt'];
            }
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api user registration error: ' . $e->getMessage());
    }

    // 月別ログイン活動状況（成功・失敗、直近6か月）
    $loginActivityData = [];
    foreach ($monthLabels as $month) {
        $loginActivityData[] = [
            'month' => $month,
            'success_count' => 0,
            'fail_count' => 0
        ];
    }
    try {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(attempted_at, '%Y-%m') AS month,
                   SUM(success = 1) AS success_count,
                   SUM(success = 0) AS fail_count
            FROM login_logs
            WHERE attempted_at >= :start_date
            GROUP BY DATE_FORMAT(attempted_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        $loginResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($loginResults as $row) {
            $month = $row['month'];
            $index = array_search($month, $monthLabels, true);
            if ($index !== false) {
                $loginActivityData[$index] = [
                    'month' => $month,
                    'success_count' => (int) $row['success_count'],
                    'fail_count' => (int) $row['fail_count']
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api login activity error: ' . $e->getMessage());
    }

    // 月別お問い合わせ件数（直近6か月）
    $inquiryMonthlyData = [];
    foreach ($monthLabels as $month) {
        $inquiryMonthlyData[] = ['month' => $month, 'count' => 0];
    }
    try {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
            FROM inquiries
            WHERE created_at >= :start_date
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        $inquiryResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($inquiryResults as $row) {
            $month = $row['month'];
            $index = array_search($month, $monthLabels, true);
            if ($index !== false) {
                $inquiryMonthlyData[$index]['count'] = (int) $row['cnt'];
            }
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api inquiry monthly error: ' . $e->getMessage());
    }

    // お問い合わせ対応状況（ステータス別件数）
    $inquiryStatusData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) AS cnt
            FROM inquiries
            GROUP BY status
            ORDER BY cnt DESC
        ");
        $stmt->execute();
        $inquiryStatusResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($inquiryStatusResults as $row) {
            $inquiryStatusData[] = [
                'label' => $row['status'] ?: '未設定',
                'count' => (int) $row['cnt']
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api inquiry status error: ' . $e->getMessage());
    }

    // 月別通報件数（直近6か月）
    $reportMonthlyData = [];
    foreach ($monthLabels as $month) {
        $reportMonthlyData[] = ['month' => $month, 'count' => 0];
    }
    try {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
            FROM reports
            WHERE created_at >= :start_date
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        $reportResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reportResults as $row) {
            $month = $row['month'];
            $index = array_search($month, $monthLabels, true);
            if ($index !== false) {
                $reportMonthlyData[$index]['count'] = (int) $row['cnt'];
            }
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api report monthly error: ' . $e->getMessage());
    }

    // 通報対応状況（ステータス別件数）
    $reportStatusData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) AS cnt
            FROM reports
            GROUP BY status
            ORDER BY cnt DESC
        ");
        $stmt->execute();
        $reportStatusResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reportStatusResults as $row) {
            $reportStatusData[] = [
                'label' => $row['status'] ?: '未設定',
                'count' => (int) $row['cnt']
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api report status error: ' . $e->getMessage());
    }

    // 通報理由ランキング TOP10
    $reportReasonData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT reason, COUNT(*) AS cnt
            FROM reports
            GROUP BY reason
            ORDER BY cnt DESC
            LIMIT 10
        ");
        $stmt->execute();
        $reportReasonResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reportReasonResults as $row) {
            $reportReasonData[] = [
                'label' => $row['reason'] ?: '未設定',
                'count' => (int) $row['cnt']
            ];
        }
    } catch (Throwable $e) {
        error_log('admin analysis-api report reason error: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    error_log('admin analysis-api error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'データの取得に失敗しました。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$response = [
    'success' => true,
    'monthLabels' => $monthLabels,
    'purchaseCounts' => $purchaseData,
    'completedCounts' => $completedData,
    'salesAmounts' => $salesData,
    'categoryListingData' => $categoryListingData,
    'salesStatusData' => $salesStatusData,
    'productRankingData' => $productRankingData,
    'categorySalesData' => $categorySalesData,
    'userRegistrationData' => $userRegistrationData,
    'userPurchaseRankingData' => $userPurchaseRankingData,
    'userListingRankingData' => $userListingRankingData,
    'userRatingRankingData' => $userRatingRankingData,
    'loginActivityData' => $loginActivityData,
    'inquiryMonthlyData' => $inquiryMonthlyData,
    'inquiryStatusData' => $inquiryStatusData,
    'reportMonthlyData' => $reportMonthlyData,
    'reportStatusData' => $reportStatusData,
    'reportReasonData' => $reportReasonData,
    'updated_at' => date('Y-m-d H:i:s')
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
