<?php

// セッションを開始
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ログインしているか確認
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// DB接続用の共通ファイルを読み込む
require_once '../../meetory-functions.php';

// ログイン中のユーザーIDを取得
$user_id = (int) $_SESSION['user_id'];

// DB接続
$pdo = getPDO();

// CSRFトークンを検証
$csrf_token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '不正なリクエストです。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 入力を取得
$goods_id = (int) ($_POST['goods_id'] ?? 0);
$bid_amount = (int) ($_POST['bid_amount'] ?? 0);

if ($goods_id <= 0 || $bid_amount <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '無効な入札です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->beginTransaction();

    // オークション情報と出品者IDを排他ロックして取得（同時入札対策）
    $sql = '
        SELECT
            a.auction_id,
            a.goods_id,
            a.current_price,
            a.start_price,
            a.buyout_price,
            a.start_at,
            a.end_at,
            a.auto_extend,
            a.status,
            a.winner_user_id,
            a.winning_price,
            a.ended_at,
            g.seller_id,
            g.is_sold
        FROM auctions AS a
        INNER JOIN goods AS g
            ON a.goods_id = g.goods_id
        WHERE a.goods_id = ?
        FOR UPDATE
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goods_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auction) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'オークション情報が見つかりません。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 売却済みまたは終了済みは入札禁止
    if ((int) $auction['is_sold'] === 1 || $auction['status'] !== 'active') {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'オークションは終了しています。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 出品者本人の入札は禁止
    if ((int) $auction['seller_id'] === $user_id) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '出品者本人は入札できません。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 開始前は入札拒否
    $tz = new DateTimeZone('Asia/Tokyo');
    $now = new DateTimeImmutable('now', $tz);
    $start_at = new DateTimeImmutable($auction['start_at'], $tz);
    if ($now < $start_at) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'オークションはまだ開始していません。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 終了日時をチェック（終了済みは入札禁止）
    $end_at = new DateTimeImmutable($auction['end_at'], $tz);
    if ($now >= $end_at) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'オークションは終了しています。'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 入札履歴の件数を取得して最低入札金額を決定
    $bid_count_stmt = $pdo->prepare('SELECT COUNT(*) AS bid_count FROM auction_bids WHERE auction_id = ?');
    $bid_count_stmt->execute([$auction['auction_id']]);
    $bid_count = (int) $bid_count_stmt->fetchColumn();

    if ($bid_count === 0) {
        // 入札履歴が0件の場合：開始価格以上
        $min_bid = (int) $auction['start_price'];
        $min_bid_message = '開始価格以上の金額を入力してください。';
    } else {
        // 2件目以降：現在価格＋100円以上
        $min_bid = (int) $auction['current_price'] + 100;
        $min_bid_message = '現在価格より100円以上高い金額を入力してください。';
    }

    if ($bid_amount < $min_bid) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $min_bid_message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // buyout_priceがNULLの場合は即決価格なしとして通常入札を許可
    $buyout_price = $auction['buyout_price'];
    $is_buyout = false;
    if ($buyout_price !== null && $buyout_price !== '') {
        // 即決価格を超える入札は禁止
        if ($bid_amount > (int) $buyout_price) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => '即決価格を超える入札はできません。'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 即決価格と同額なら即決成立
        $is_buyout = $bid_amount === (int) $buyout_price;
    }

    // 即決成立時は自動延長を実行しない
    $new_end_at = $auction['end_at'];
    if (!$is_buyout && (int) $auction['auto_extend'] === 1) {
        $diff_seconds = $end_at->getTimestamp() - $now->getTimestamp();
        if ($diff_seconds <= 300) {
            $new_end_at = $end_at->modify('+5 minutes')->format('Y-m-d H:i:s');
        }
    }

    // 入札記録を挿入（auction_id, user_id, bid_price, bid_at）
    $sql = '
        INSERT INTO auction_bids (auction_id, user_id, bid_price, bid_at)
        VALUES (?, ?, ?, NOW())
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$auction['auction_id'], $user_id, $bid_amount]);

    // 現在価格を更新
    $sql = 'UPDATE auctions SET current_price = ? WHERE auction_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bid_amount, $auction['auction_id']]);

    // 自動延長があれば終了時刻を更新
    if ($new_end_at !== $auction['end_at']) {
        $sql = 'UPDATE auctions SET end_at = ? WHERE auction_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_end_at, $auction['auction_id']]);
    }

    // 即決成立なら落札者・落札価格・終了状態・終了時刻を更新し、商品も売却済みにする
    if ($is_buyout) {
        $ended_at = $now->format('Y-m-d H:i:s');
        $sql = 'UPDATE auctions SET winner_user_id = ?, winning_price = ?, status = ?, ended_at = ? WHERE auction_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $bid_amount, 'sold', $ended_at, $auction['auction_id']]);

        $sql = 'UPDATE goods SET is_sold = 1 WHERE goods_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goods_id]);
    }

    $pdo->commit();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $is_buyout ? '即決成立しました。' : '入札を受け付けました。',
        'is_buyout' => $is_buyout,
        'new_end_at' => $new_end_at
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '入札処理に失敗しました。'], JSON_UNESCAPED_UNICODE);
    exit;
}
