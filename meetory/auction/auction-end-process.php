<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("この処理はPHP CLIからのみ実行できます。\n");
}

require_once __DIR__ . '/../../meetory-functions.php';

try {
    $pdo = getPDO();
    $candidateStmt = $pdo->prepare(
        'SELECT auction_id
        FROM auctions
        WHERE status = :status
            AND end_at <= NOW()
        ORDER BY end_at ASC, auction_id ASC'
    );
    $candidateStmt->execute([':status' => 'active']);
    $auctionIds = $candidateStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    fwrite(STDERR, 'オークション対象の取得に失敗しました: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$soldCount = 0;
$noBidCount = 0;
$skippedCount = 0;
$failedCount = 0;

fwrite(STDOUT, '処理対象件数: ' . count($auctionIds) . PHP_EOL);

foreach ($auctionIds as $auctionId) {
    $auctionId = (int) $auctionId;

    try {
        $pdo->beginTransaction();

        $auctionStmt = $pdo->prepare(
            'SELECT
                a.auction_id,
                a.goods_id,
                a.status,
                a.end_at,
                g.goods_id AS product_goods_id,
                g.is_sold AS goods_is_sold
            FROM auctions AS a
            INNER JOIN goods AS g
                ON g.goods_id = a.goods_id
            WHERE a.auction_id = :auction_id
                AND a.status = :status
                AND a.end_at <= NOW()
            FOR UPDATE'
        );
        $auctionStmt->execute([
            ':auction_id' => $auctionId,
            ':status' => 'active',
        ]);
        $auction = $auctionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$auction) {
            $pdo->commit();
            $skippedCount++;
            fwrite(STDOUT, 'auction_id=' . $auctionId . ': 対象外のためスキップ' . PHP_EOL);
            continue;
        }

        $bidStmt = $pdo->prepare(
            'SELECT bid_id, user_id, bid_price
            FROM auction_bids
            WHERE auction_id = :auction_id
            ORDER BY bid_price DESC, bid_at ASC, bid_id ASC
            LIMIT 1'
        );
        $bidStmt->execute([':auction_id' => $auctionId]);
        $highestBid = $bidStmt->fetch(PDO::FETCH_ASSOC);

        if ($highestBid) {
            $updateAuctionStmt = $pdo->prepare(
                'UPDATE auctions
                SET winner_user_id = :winner_user_id,
                    winning_price = :winning_price,
                    status = :new_status,
                    ended_at = NOW()
                WHERE auction_id = :auction_id'
            );
            $updateAuctionStmt->execute([
                ':winner_user_id' => $highestBid['user_id'],
                ':winning_price' => $highestBid['bid_price'],
                ':new_status' => 'sold',
                ':auction_id' => $auctionId,
            ]);

            $updateGoodsStmt = $pdo->prepare(
                'UPDATE goods
                SET is_sold = 1
                WHERE goods_id = :goods_id'
            );
            $updateGoodsStmt->execute([
                ':goods_id' => $auction['product_goods_id'],
            ]);

            $pdo->commit();
            $soldCount++;
            fwrite(
                STDOUT,
                'auction_id=' . $auctionId
                    . ': sold, winner_user_id=' . $highestBid['user_id']
                    . ', winning_price=' . $highestBid['bid_price']
                    . PHP_EOL
            );
        } else {
            $updateAuctionStmt = $pdo->prepare(
                'UPDATE auctions
                SET winner_user_id = NULL,
                    winning_price = NULL,
                    status = :new_status,
                    ended_at = NOW()
                WHERE auction_id = :auction_id'
            );
            $updateAuctionStmt->execute([
                ':new_status' => 'ended',
                ':auction_id' => $auctionId,
            ]);

            $pdo->commit();
            $noBidCount++;
            fwrite(STDOUT, 'auction_id=' . $auctionId . ': ended (no bids)' . PHP_EOL);
        }
    } catch (Throwable $e) {
        $rollbackFailed = false;

        if ($pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (Throwable $rollbackError) {
                $rollbackFailed = true;
                fwrite(
                    STDERR,
                    'auction_id=' . $auctionId
                        . ' のロールバックに失敗しました: '
                        . $rollbackError->getMessage()
                        . PHP_EOL
                );
            }
        }

        $failedCount++;
        fwrite(
            STDERR,
            'auction_id=' . $auctionId
                . ' の処理に失敗しました: '
                . $e->getMessage()
                . PHP_EOL
        );

        if ($rollbackFailed) {
            break;
        }
    }
}

fwrite(
    STDOUT,
    '完了: 落札=' . $soldCount
        . ', 入札なし終了=' . $noBidCount
        . ', スキップ=' . $skippedCount
        . ', エラー=' . $failedCount
        . PHP_EOL
);

exit($failedCount > 0 ? 1 : 0);
