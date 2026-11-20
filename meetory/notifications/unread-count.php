<?php

/**
 * Ajaxエンドポイント：通知の未読件数と最新通知一覧をJSONで返す。
 * 通知センターの自動更新（10秒間隔ポーリング）用。
 *
 * セキュリティ：
 * - ログイン必須（未ログイン時は401）
 * - ログインユーザー本人の通知のみ取得（user_idはセッションから）
 * - SQLはプリペアドステートメント
 * - JSON形式で返し、PHPの警告/エラーをレスポンスに混入させない
 */

// エラー出力を無効化（JSONレスポンスに混入させないため）
ini_set('display_errors', '0');
error_reporting(0);

// セッション開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// JSONコンテンツタイプを事前に設定（エラー時もJSONで返す）
header('Content-Type: application/json; charset=utf-8');

// ログイン確認
if (
    !isset($_SESSION['user_id']) ||
    filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) === false ||
    (int) $_SESSION['user_id'] <= 0
) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => '未ログインです',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 共通処理
require_once '../../meetory-functions.php';

$pdo = getPDO();
$user_id = (int) $_SESSION['user_id'];

$settings = getNotificationSettings($pdo, $user_id);
$notificationsEnabled = (int) $settings['enabled'] === 1;
$soundEnabled = (int) $settings['sound_enabled'] === 1;

// 許可されている通知タイプを設定から構築
$allowedTypeKeys = [];
$typeColumnMap = [
    'message'    => 'type_message',
    'purchase'   => 'type_purchase',
    'shipping'   => 'type_shipping',
    'received'   => 'type_received',
    'review'     => 'type_review',
    'favorite'   => 'type_favorite',
    'price_down' => 'type_price_down',
    'follow'     => 'type_follow',
    'admin'      => 'type_admin',
    'auction_bid_surpassed'   => 'type_auction_bid_surpassed',
    'auction_won'             => 'type_auction_won',
    'auction_ending'          => 'type_auction_ending',
    'auction_bid_success'     => 'type_auction_bid_success',
    'auction_ended_seller'    => 'type_auction_ended_seller',
];

foreach ($typeColumnMap as $typeKey => $colName) {
    if ((int) $settings[$colName] === 1) {
        $allowedTypeKeys[] = $typeKey;
    }
}

// 通知種類フィルター（省略可、デフォルト all）
$allowedTypes = [
    'all' => null,
    'message' => 'message',
    'purchase' => 'purchase',
    'shipping' => 'shipping',
    'received' => 'received',
    'review' => 'review',
    'favorite' => 'favorite',
    'price_down' => 'price_down',
    'follow' => 'follow',
    'admin' => 'admin',
    'auction_bid_surpassed' => 'auction_bid_surpassed',
    'auction_won' => 'auction_won',
    'auction_ending' => 'auction_ending',
    'auction_bid_success' => 'auction_bid_success',
    'auction_ended_seller' => 'auction_ended_seller',
];

$filter = $_GET['type'] ?? 'all';
if (!array_key_exists($filter, $allowedTypes)) {
    $filter = 'all';
}
$filterType = $allowedTypes[$filter]; // null なら全種類

// 未読件数（COUNT。大量の通知があっても効率的） - 設定でONのタイプのみ
if (!$notificationsEnabled || empty($allowedTypeKeys)) {
    $unreadCount = 0;
} else {
    $typePlaceholders = implode(',', array_map(fn($i) => ":type_$i", array_keys($allowedTypeKeys)));
    $unreadStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0 AND type IN (' . $typePlaceholders . ')'
    );
    $unreadStmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    foreach ($allowedTypeKeys as $i => $typeKey) {
        $unreadStmt->bindValue(":type_$i", $typeKey, PDO::PARAM_STR);
    }
    $unreadStmt->execute();
    $unreadCount = (int) $unreadStmt->fetchColumn();
}

// 通知一覧（最新20件。created_at降順、同日時はnotification_id降順） - 設定でONのタイプのみ
$limit = 20;
$notifications = [];

if ($notificationsEnabled && !empty($allowedTypeKeys)) {

    if ($filterType === null) {
        // 全種類（設定でONになっているもののみ）
        $typePlaceholders = implode(',', array_map(fn($i) => ":type_$i", array_keys($allowedTypeKeys)));
        $sql = '
            SELECT
                notification_id,
                user_id,
                type,
                title,
                message,
                related_id,
                is_read,
                created_at
            FROM notifications
            WHERE user_id = :user_id
              AND type IN (' . $typePlaceholders . ')
            ORDER BY created_at DESC, notification_id DESC
            LIMIT :limit
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($allowedTypeKeys as $i => $typeKey) {
            $stmt->bindValue(":type_$i", $typeKey, PDO::PARAM_STR);
        }
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 指定種類のみ（かつ設定でONの場合のみ）
        if (in_array($filterType, $allowedTypeKeys, true)) {
            $sql = '
                SELECT
                    notification_id,
                    user_id,
                    type,
                    title,
                    message,
                    related_id,
                    is_read,
                    created_at
                FROM notifications
                WHERE user_id = :user_id
                  AND type = :type
                ORDER BY created_at DESC, notification_id DESC
                LIMIT :limit
            ';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':type', $filterType, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// 通知種類ラベル
$typeLabels = [
    'all' => 'すべて',
    'message' => 'メッセージ',
    'purchase' => '購入',
    'shipping' => '発送',
    'received' => '受取',
    'review' => '評価',
    'favorite' => 'お気に入り',
    'price_down' => '値下げ',
    'follow' => 'フォロー',
    'admin' => '運営からのお知らせ',
    'auction_bid_surpassed' => '入札額を上回られた',
    'auction_won' => '落札しました',
    'auction_ending' => 'ウォッチ中の商品が終了',
    'auction_bid_success' => '入札を受け付けました',
    'auction_ended_seller' => 'オークションが終了しました',
];

$items = [];
foreach ($notifications as $n) {
    $items[] = [
        'notification_id' => (int) $n['notification_id'],
        'type' => $n['type'],
        'type_label' => $typeLabels[$n['type']] ?? $n['type'],
        'title' => $n['title'],
        'message' => $n['message'],
        'related_id' => $n['related_id'] !== null ? (int) $n['related_id'] : null,
        'is_read' => (int) $n['is_read'] === 1,
        'created_at' => $n['created_at'],
    ];
}

echo json_encode([
    'success' => true,
    'unread_count' => $unreadCount,
    'sound_enabled' => $soundEnabled ? 1 : 0,
    'notifications' => $items,
], JSON_UNESCAPED_UNICODE);
exit;
