<?php

/**
 * 通知センター：共通通知生成処理
 *
 * notifications テーブルへ通知レコードを保存する。
 * 他の PHP ファイルから include / require して createNotification() を呼び出す。
 *
 * 通知設定（notification_settings）による保存の可否はここで制御せず、
 * 設定が OFF でもレコードは保存する。表示・通知音などの制御は
 * 後続の実装段階で行う。
 */

/**
 * 通知を作成して notifications テーブルに保存する。
 *
 * @param PDO $pdo        DB接続（呼び出し元で生成・トランザクション管理）
 * @param int $userId     通知を受け取るユーザーID（正の整数）
 * @param string $type    通知種別（9種のいずれか）
 * @param string $title   通知タイトル
 * @param string $message 通知本文
 * @param int|null $relatedId 関連する商品・取引・メッセージなどのID（不要なら null）
 * @return bool 成功したら true、失敗または不正な種別なら false
 */
function createNotification(
    PDO $pdo,
    $userId,
    $type,
    $title,
    $message,
    $relatedId = null
) {
    // 1. 許可された通知種別か確認（不正な type は通知を作成しない）
    $allowedTypes = [
        'message',
        'purchase',
        'shipping',
        'received',
        'review',
        'favorite',
        'price_down',
        'follow',
        'admin',
        'auction_bid_surpassed',
        'auction_won',
        'auction_ending',
        'auction_bid_success',
        'auction_ended_seller',
    ];

    if (!is_string($type) || !in_array($type, $allowedTypes, true)) {
        error_log('createNotification: 不正な通知種別です: ' . var_export($type, true));
        return false;
    }

    // 2. user_id の妥当性確認（変換前に正の整数であることを確認）
    if (is_string($userId)) {
        if (!preg_match('/\A[1-9][0-9]*\z/', $userId)) {
            error_log('createNotification: 不正なuser_idです: ' . var_export($userId, true));
            return false;
        }
    } elseif (is_int($userId)) {
        if ($userId <= 0) {
            error_log('createNotification: 不正なuser_idです: ' . var_export($userId, true));
            return false;
        }
    } else {
        error_log('createNotification: 不正なuser_idです: ' . var_export($userId, true));
        return false;
    }
    $userId = (int) $userId;

    // 3. title と message の入力チェック（空・空白のみ禁止）
    if (!is_string($title) || trim($title) === '') {
        error_log('createNotification: title が未入力です。');
        return false;
    }
    if (!is_string($message) || trim($message) === '') {
        error_log('createNotification: message が未入力です。');
        return false;
    }

    // カラム幅の超過を防ぐ（title: varchar(255)）
    if (mb_strlen(trim($title), 'UTF-8') > 255) {
        error_log('createNotification: title が255文字を超えています。');
        return false;
    }

    // related_id は整数または NULL のみ許可（0・負の数・不正な値は失敗させる）
    if ($relatedId !== null) {
        if (is_string($relatedId)) {
            if (!preg_match('/\A[1-9][0-9]*\z/', $relatedId)) {
                error_log('createNotification: 不正なrelated_idです: ' . var_export($relatedId, true));
                return false;
            }
        } elseif (is_int($relatedId)) {
            if ($relatedId <= 0) {
                error_log('createNotification: 不正なrelated_idです: ' . var_export($relatedId, true));
                return false;
            }
        } else {
            error_log('createNotification: 不正なrelated_idです: ' . var_export($relatedId, true));
            return false;
        }
        $relatedId = (int) $relatedId;
    }

    // 4. 5. 6. プリペアドステートメントを使用して INSERT（SQLインジェクション対策）
    $sql = "
        INSERT INTO notifications
            (user_id, type, title, message, related_id, is_read, created_at)
        VALUES
            (:user_id, :type, :title, :message, :related_id, 0, CURRENT_TIMESTAMP)
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':message' => $message,
            ':related_id' => $relatedId,
        ]);
    } catch (PDOException $e) {
        // DBの詳しいエラーはログに記録し、画面には出さない
        error_log('createNotification: DBエラー: ' . $e->getMessage());
        return false;
    }

    return (bool) $result;
}
