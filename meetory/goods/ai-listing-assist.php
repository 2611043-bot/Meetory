
<?php
// =============================================
// Meetory AI出品アシスト
// ファイル：meetory/goods/ai-listing-assist.php
// =============================================

// PHPの実行時間制限を120秒に設定
set_time_limit(120);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

// JSONレスポンスを返して処理を終了
function sendJson($success, $data = [], $status = 200)
{
    http_response_code($status);

    echo json_encode(
        array_merge(['success' => $success], $data),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// POST以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(false, ['error' => 'POSTリクエストで送信してください。'], 405);
}

// ログイン確認
if (empty($_SESSION['user_id'])) {
    sendJson(false, ['error' => 'ログインしてください。'], 401);
}

// JSONデータを受け取る（サイズ制限：30KB）
$raw_request = file_get_contents('php://input');

if ($raw_request === false || strlen($raw_request) > 30000) {
    sendJson(false, ['error' => '送信データが大きすぎます。'], 413);
}

try {
    $request = json_decode($raw_request, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    sendJson(false, ['error' => '送信データのJSON形式が正しくありません。'], 400);
}

if (!is_array($request)) {
    sendJson(false, ['error' => '送信データが正しくありません。'], 400);
}

// CSRF確認
$csrf_token = $request['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrf_token) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    sendJson(false, ['error' => '不正なリクエストです。'], 403);
}

$user_id = $_SESSION['user_id'];

// メッセージの型・内容・文字数を確認
if (!isset($request['message']) || !is_string($request['message'])) {
    sendJson(false, ['error' => 'メッセージの形式が正しくありません。'], 400);
}

$message = trim($request['message']);

if ($message === '' || mb_strlen($message, 'UTF-8') > 5000) {
    sendJson(false, ['error' => 'メッセージは1文字以上5000文字以内で入力してください。'], 400);
}

// 商品情報の型・サイズを確認
$product_info = $request['product_info'] ?? [];

if (!is_array($product_info) && !is_string($product_info)) {
    sendJson(false, ['error' => '商品情報が正しくありません。'], 400);
}

$product_info_json = json_encode(
    $product_info,
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);

if ($product_info_json === false || strlen($product_info_json) > 20000) {
    sendJson(false, ['error' => '商品情報が大きすぎるか、正しく処理できません。'], 400);
}

// 会話IDの形式を先に確認
$ai_data_id = $request['ai_data_id'] ?? null;
$is_new_conversation = ($ai_data_id === null || $ai_data_id === '');

if (!$is_new_conversation) {
    if (
        (!is_int($ai_data_id) && !is_string($ai_data_id)) ||
        !ctype_digit((string)$ai_data_id) ||
        (int)$ai_data_id <= 0
    ) {
        sendJson(false, ['error' => '会話IDが正しくありません。'], 400);
    }

    $ai_data_id = (int)$ai_data_id;
}

try {
    // DB接続
    require_once __DIR__ . '/../../meetory-functions.php';
    $pdo = getPDO();

    // =============================================
    // 1. 会話データの新規作成または所有者確認
    // =============================================

    if ($is_new_conversation) {
        // 新規会話はAI通信成功後の保存トランザクション内で作成する
        $history = [];
    } else {
        $sql = "
            SELECT ai_data_id
            FROM ai_data
            WHERE ai_data_id = :ai_data_id
                AND user_id = :user_id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ai_data_id' => $ai_data_id,
            ':user_id' => $user_id
        ]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            sendJson(false, ['error' => '会話が見つかりません。'], 404);
        }
    }

    // =============================================
    // 2. 保存済みの会話履歴を取得
    // =============================================

    if (!$is_new_conversation) {
        $sql = "
            SELECT role, message
            FROM ai_chat_messages
            WHERE ai_data_id = :ai_data_id
            ORDER BY message_id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':ai_data_id' => $ai_data_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Geminiに渡す形式へ変換
    $contents = [];

    foreach ($history as $chat) {
        if (
            !in_array($chat['role'], ['user', 'model'], true) ||
            !is_string($chat['message'])
        ) {
            continue;
        }

        $contents[] = [
            'role' => $chat['role'],
            'parts' => [['text' => $chat['message']]]
        ];
    }

    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $message]]
    ];

    // =============================================
    // 3. Meetory専用システムプロンプト
    // =============================================

    $system_instruction = <<<'PROMPT'
あなたはフリマサイト「Meetory」専属のAI出品アシスタントです。

【役割】
あなたの役割は、出品者との会話を通じて商品情報を整理し、不足情報を聞き取り、商品名や商品説明文の作成を補助することです。
出品者が主体であり、あなたは出品作業を支援するアシスタントです。

【基本方針】
・出品者が入力した情報を最優先してください。
・分からない情報を推測して補完しないでください。
・確認できていない商品の状態、性能、付属品などを事実として書かないでください。
・日本語で、分かりやすく簡潔に回答してください。
・出品者の負担を減らすことを最優先としてください。

【商品情報の確認】
・最初に渡された商品名、カテゴリー、説明文、メーカー、商品の状態、価格などを確認してください。
・すでに入力されている情報を再度質問しないでください。
・不足している情報がある場合のみ、必要な質問をしてください。
・商品のカテゴリーに応じて質問内容を変えてください。
・例えば、電子機器なら動作状況、衣類ならサイズや汚れ、傷などを確認してください。
・分からない項目は「不明」のままでも構いません。

【矛盾や不自然な情報】
・商品名とカテゴリーが一致しない場合は確認してください。
・商品説明と商品の状態に矛盾がある場合は確認してください。
・不自然な情報や疑問点がある場合は、出品者に質問してください。
・出品者の許可なく商品情報やカテゴリーを変更しないでください。

【文章生成のタイミング】
・必要な情報が集まったら、商品名と商品説明文を生成してよいか確認してください。
・出品者の許可を得る前に完成文章を生成しないでください。
・許可を得たら、商品名と商品説明文を同時に生成してください。

【文章生成ルール】
・誇張表現を使用しないでください。
・根拠のない性能や品質を記載しないでください。
・不明な情報を勝手に補わないでください。
・傷や汚れなどは正直に記載してください。
・出品者が入力した情報を分かりやすく整理してください。
・生成した文章はあくまで提案として提示してください。

【生成後の対応】
・商品名と商品説明文は編集可能な提案として提示してください。
・商品名と商品説明文はそれぞれ独立して修正・反映できるようにしてください。
・出品者が自分で内容を確認し、必要に応じて編集できるようにしてください。
・出品登録を自動実行してはいけません。

【禁止事項】
・商品情報を捏造すること。
・不明な情報を事実として断定すること。
・許可なく商品情報を変更すること。
・許可なく文章を生成すること。
・出品登録を自動で実行すること。
・最新の市場価格を調査したかのように振る舞うこと。
・商品画像を確認したかのように振る舞うこと。

【会話の流れ】
1. 最初に商品情報を確認する。
2. 入力済みの情報を整理する。
3. 不足情報があれば質問する。
4. 矛盾があれば確認する。
5. 情報が集まったら文章生成の許可を求める。
6. 許可されたら商品名と商品説明文を生成する。
7. 生成結果を出品者に提示する。

【返答形式】
必ずJSON形式で返答してください。
JSON以外の文章やMarkdown記号をJSONの外側に付けないでください。

次の3つの項目を必ず含めてください。

{
  "message": "チャットに表示する会話文",
  "suggested_title": null,
  "suggested_description": null
}

【各項目のルール】
・messageには、質問、案内、説明など、出品者との会話文を入れてください。
・suggested_titleには、生成許可を得た後の商品名を文字列で入れてください。
・suggested_descriptionには、生成許可を得た後の商品説明文を文字列で入れてください。
・文章生成の許可を得る前は、suggested_titleとsuggested_descriptionを必ずnullにしてください。
・許可を得た後は、商品名と商品説明文の両方を生成し、それぞれの項目に分けて入れてください。
・文章生成が完了したことをmessageでも出品者に伝えてください。
・商品名や説明文をmessageの中だけに記載するのではなく、必ず専用項目にも入れてください。
・該当する提案がない場合はnullにしてください。
・JSONの文字列内では、必要に応じて改行を\nとして表現してください。

質問を一度に大量に行わず、必要なものを優先して確認してください。
出品者の意図を尊重し、出品者の負担を減らすことを最優先としてください。
PROMPT;

    // 商品情報をシステム指示に追加
    $product_info_text = is_string($product_info)
        ? $product_info
        : $product_info_json;

    $system_instruction .=
        "\n\n【出品者が入力した商品情報】\n"
        . $product_info_text
        . "\n\nこの商品情報を確認し、不足情報があれば質問してください。";

    // =============================================
    // 4. Gemini API接続の準備
    // =============================================

    require_once __DIR__ . '/../gemini-secret.php';

    $api_key = $gemini_api_key ?? '';

    if ($api_key === '') {
        sendJson(false, ['error' => 'Gemini APIキーを取得できません。'], 500);
    }

    $model = 'gemini-3.8-flash';

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

    $request_body = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $system_instruction]
            ]
        ],
        'contents' => $contents,
        'generationConfig' => [
            'responseMimeType' => 'application/json'
        ]
    ];

    $json_body = json_encode(
        $request_body,
        JSON_UNESCAPED_UNICODE
    );

    if ($json_body === false) {
        sendJson(false, ['error' => 'AIへの送信データを作成できませんでした。'], 500);
    }

    // =============================================
    // 5. Gemini API通信（最大3回まで再試行）
    // =============================================

    $max_attempts = 3;

    // 再試行するHTTPステータス
    $retry_http_codes = [429, 500, 502, 503, 504];

    // 再試行するcURLエラー
    $retry_curl_errors = [
        CURLE_OPERATION_TIMEOUTED,
        CURLE_COULDNT_CONNECT,
        CURLE_RECV_ERROR,
        CURLE_SEND_ERROR,
        CURLE_GOT_NOTHING
    ];

    $response = false;
    $curl_error = '';
    $curl_errno = 0;
    $http_code = 0;

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {

        $ch = curl_init($url);

        if ($ch === false) {
            $curl_error = 'cURLの初期化に失敗しました。';
            $curl_errno = 0;
            break;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $api_key
            ],
            CURLOPT_POSTFIELDS => $json_body,
            CURLOPT_CAINFO => __DIR__ . '/../cacert.pem',

            // 1回の通信は最大25秒
            CURLOPT_TIMEOUT => 25,

            // 接続確立は最大10秒
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // 通信成功かつHTTPステータスが2xxなら終了
        if (
            $response !== false &&
            $http_code >= 200 &&
            $http_code < 300
        ) {
            break;
        }

        // 再試行する必要があるか判定
        $should_retry = false;

        if ($response === false) {
            // 一時的な通信エラーの場合
            $should_retry = in_array(
                $curl_errno,
                $retry_curl_errors,
                true
            );
        } else {
            // 一時的なHTTPエラーの場合
            $should_retry = in_array(
                $http_code,
                $retry_http_codes,
                true
            );
        }

        // 再試行対象でない、または最後の試行なら終了
        if (!$should_retry || $attempt >= $max_attempts) {
            break;
        }

        error_log(
            'Gemini API retry: attempt '
            . $attempt
            . ', HTTP '
            . $http_code
            . ', cURL error '
            . $curl_errno
        );

        // 1回目は2秒、2回目は4秒待機
        sleep($attempt * 2);
    }

    // =============================================
    // 6. Gemini APIのエラー確認
    // =============================================

    if ($response === false) {
        error_log('Gemini cURL error: ' . $curl_error);
        error_log('Gemini cURL error number: ' . $curl_errno);

        sendJson(false, [
            'error' => 'AIとの通信に失敗しました。時間をおいて再度お試しください。'
        ], 502);
    }

    if ($http_code < 200 || $http_code >= 300) {
        error_log('Gemini API HTTP error: ' . $http_code);
        error_log('Gemini API response: ' . $response);

        if (in_array($http_code, $retry_http_codes, true)) {
            sendJson(false, [
                'error' => 'AIが混み合っています。少し時間をおいて再度お試しください。'
            ], 503);
        }

        sendJson(false, [
            'error' => 'AI APIからエラーが返されました。'
        ], 502);
    }

    // =============================================
    // 7. GeminiのJSON形式の返答を取得
    // =============================================

    $result = json_decode($response, true);

    $ai_raw_message = '';

    if (!empty($result['candidates'][0]['content']['parts'])) {

        foreach ($result['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $ai_raw_message .= $part['text'];
            }
        }
    }

    if ($ai_raw_message === '') {
        sendJson(false, [
            'error' => 'AIから有効な返答を取得できませんでした。'
        ], 502);
    }

    // Geminiが返したJSONを解析
    try {
        $ai_data = json_decode(
            $ai_raw_message,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        error_log('Gemini response JSON error: ' . $e->getMessage());

        sendJson(false, [
            'error' => 'AIの返答を正しく読み取れませんでした。もう一度お試しください。'
        ], 502);
    }

    // JSONの形式を確認
    if (
        !is_array($ai_data) ||
        !isset($ai_data['message']) ||
        !is_string($ai_data['message'])
    ) {
        sendJson(false, [
            'error' => 'AIの返答形式が正しくありません。'
        ], 502);
    }

    // チャット表示用メッセージ
    $ai_message = trim($ai_data['message']);

    if ($ai_message === '' || mb_strlen($ai_message, 'UTF-8') > 10000) {
        sendJson(false, [
            'error' => 'AIの会話文を正しく取得できませんでした。'
        ], 502);
    }

    // 商品名の提案を取得
    $suggested_title = $ai_data['suggested_title'] ?? null;

    if ($suggested_title !== null) {
        if (!is_string($suggested_title)) {
            sendJson(false, [
                'error' => 'AIの商品名の形式が正しくありません。'
            ], 502);
        }

        $suggested_title = trim($suggested_title);

        if (
            $suggested_title === '' ||
            mb_strlen($suggested_title, 'UTF-8') > 100
        ) {
            sendJson(false, [
                'error' => 'AIの商品名の文字数が正しくありません。'
            ], 502);
        }
    }

    // 商品説明文の提案を取得
    $suggested_description = $ai_data['suggested_description'] ?? null;

    if ($suggested_description !== null) {
        if (!is_string($suggested_description)) {
            sendJson(false, [
                'error' => 'AIの商品説明文の形式が正しくありません。'
            ], 502);
        }

        $suggested_description = trim($suggested_description);

        if (
            $suggested_description === '' ||
            mb_strlen($suggested_description, 'UTF-8') > 5000
        ) {
            sendJson(false, [
                'error' => 'AIの商品説明文の文字数が正しくありません。'
            ], 502);
        }
    }

    // =============================================
    // 8. AIの返答をまとめてDBに保存
    // =============================================

    // 会話履歴にも提案内容を残す
    $stored_ai_message = $ai_message;

    if ($suggested_title !== null) {
        $stored_ai_message .=
            "\n\n【商品名の提案】\n"
            . $suggested_title;
    }

    if ($suggested_description !== null) {
        $stored_ai_message .=
            "\n\n【商品説明文の提案】\n"
            . $suggested_description;
    }

    // Gemini通信成功後に、ユーザー発言・AI返答・商品情報を一括保存
    $pdo->beginTransaction();

    try {
        // 新規会話の場合は、ここで初めて会話レコードを作成
        if ($is_new_conversation) {
            $sql = "
                INSERT INTO ai_data
                    (user_id, goods_id, input_data, created_at, updated_at)
                VALUES
                    (:user_id, NULL, :input_data, NOW(), NOW())
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':input_data' => $product_info_json
            ]);

            $ai_data_id = (int)$pdo->lastInsertId();
        }

        $sql = "
            INSERT INTO ai_chat_messages
                (ai_data_id, role, message, created_at)
            VALUES
                (:user_ai_data_id, 'user', :message, NOW()),
                (:model_ai_data_id, 'model', :ai_message, NOW())
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_ai_data_id' => $ai_data_id,
            ':model_ai_data_id' => $ai_data_id,
            ':message' => $message,
            ':ai_message' => $stored_ai_message
        ]);

        $sql = "
            UPDATE ai_data
            SET input_data = :input_data,
                ai_result = :ai_result,
                updated_at = NOW()
            WHERE ai_data_id = :ai_data_id
                AND user_id = :user_id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':input_data' => $product_info_json,
            ':ai_result' => $stored_ai_message,
            ':ai_data_id' => $ai_data_id,
            ':user_id' => $user_id
        ]);

        if ($stmt->rowCount() === 0) {
            // 値が同一でも更新対象の所有権は事前確認済み
            $check = $pdo->prepare(
                'SELECT ai_data_id FROM ai_data WHERE ai_data_id = :id AND user_id = :user_id'
            );

            $check->execute([
                ':id' => $ai_data_id,
                ':user_id' => $user_id
            ]);

            if (!$check->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('会話データの更新対象が見つかりません。');
            }
        }

        $pdo->commit();

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    // =============================================
    // 9. 画面側へ返答
    // =============================================

    sendJson(true, [
        'ai_data_id' => $ai_data_id,
        'message' => $ai_message,
        'suggested_title' => $suggested_title,
        'suggested_description' => $suggested_description
    ]);

} catch (Throwable $e) {

    error_log('Meetory AI listing assist error: ' . $e->getMessage());

    sendJson(false, [
        'error' => 'AI出品アシストの処理中にエラーが発生しました。'
    ], 500);
}
