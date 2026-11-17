
<?php
// =============================================
// Meetory AI出品アシスト
// ファイル：meetory/goods/ai-listing-assist.php
// =============================================

session_start();

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

// JSONデータを受け取る
$request = json_decode(file_get_contents('php://input'), true);

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

// ユーザー情報
$user_id = $_SESSION['user_id'];

// メッセージ確認
$message = trim($request['message'] ?? '');

if ($message === '' || mb_strlen($message) > 5000) {
    sendJson(false, ['error' => 'メッセージを入力してください。'], 400);
}

// 商品情報
$product_info = $request['product_info'] ?? [];

if (!is_array($product_info) && !is_string($product_info)) {
    sendJson(false, ['error' => '商品情報が正しくありません。'], 400);
}

$product_info_json = json_encode(
    $product_info,
    JSON_UNESCAPED_UNICODE
);

if ($product_info_json === false) {
    sendJson(false, ['error' => '商品情報を処理できませんでした。'], 400);
}

// 会話ID
$ai_data_id = $request['ai_data_id'] ?? null;

try {
    // DB接続
    require_once __DIR__ . '/../../meetory-functions.php';

    $pdo = getPDO();

    // =============================================
    // 1. 会話データの新規作成または確認
    // =============================================

    if ($ai_data_id === null || $ai_data_id === '') {

        // 新しい会話を作成
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

    } else {

        // 会話IDの形式確認
        if (
            !is_scalar($ai_data_id) ||
            !ctype_digit((string)$ai_data_id) ||
            (int)$ai_data_id <= 0
        ) {
            sendJson(false, ['error' => '会話IDが正しくありません。'], 400);
        }

        $ai_data_id = (int)$ai_data_id;

        // ログイン中のユーザーが所有する会話か確認
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

        // 商品情報を更新
        $sql = "
            UPDATE ai_data
            SET input_data = :input_data,
                updated_at = NOW()
            WHERE ai_data_id = :ai_data_id
                AND user_id = :user_id
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':input_data' => $product_info_json,
            ':ai_data_id' => $ai_data_id,
            ':user_id' => $user_id
        ]);
    }

    // =============================================
    // 2. ユーザーのメッセージをDBに保存
    // =============================================

    $sql = "
        INSERT INTO ai_chat_messages
            (ai_data_id, role, message, created_at)
        VALUES
            (:ai_data_id, 'user', :message, NOW())
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':ai_data_id' => $ai_data_id,
        ':message' => $message
    ]);

    // =============================================
    // 3. 過去の会話履歴を取得
    // =============================================

    $sql = "
        SELECT role, message
        FROM ai_chat_messages
        WHERE ai_data_id = :ai_data_id
        ORDER BY message_id ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':ai_data_id' => $ai_data_id
    ]);

    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Geminiに渡す会話形式へ変換
$contents = [];

// 初回の商品情報をGeminiに渡す
$product_info_text = is_string($product_info)
    ? $product_info
    : $product_info_json;

$contents[] = [
    'role' => 'user',
    'parts' => [
        [
            'text' => "【出品者が入力した商品情報】\n"
                . $product_info_text
                . "\n\nこの商品情報を確認し、不足情報があれば質問してください。"
        ]
    ]
];

// 過去の会話履歴を追加
foreach ($history as $chat) {

    if (!in_array($chat['role'], ['user', 'model'], true)) {
        continue;
    }

    $contents[] = [
        'role' => $chat['role'],
        'parts' => [
            ['text' => $chat['message']]
        ]
    ];
}

    foreach ($history as $chat) {

        if (
            !in_array($chat['role'], ['user', 'model'], true)
        ) {
            continue;
        }

        $contents[] = [
            'role' => $chat['role'],
            'parts' => [
                ['text' => $chat['message']]
            ]
        ];
    }

    // =============================================
    // 4. Meetory専用システムプロンプト
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

質問を一度に大量に行わず、必要なものを優先して確認してください。
出品者の意図を尊重し、出品者の負担を減らすことを最優先としてください。
PROMPT;

    // =============================================
    // 5. Gemini API接続
    // =============================================

    require_once __DIR__ . '/../gemini-secret.php';

    $api_key = $gemini_api_key ?? '';

    if ($api_key === '') {
        sendJson(false, ['error' => 'Gemini APIキーを取得できません。'], 500);
    }

    $model = 'gemini-3.5-flash';

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

    $request_body = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $system_instruction]
            ]
        ],
        'contents' => $contents
    ];

    $json_body = json_encode(
        $request_body,
        JSON_UNESCAPED_UNICODE
    );

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $api_key
        ],
        CURLOPT_POSTFIELDS => $json_body,
        CURLOPT_CAINFO => __DIR__ . '/../cacert.pem',
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        error_log('Gemini cURL error: ' . $curl_error);

        sendJson(false, ['error' => 'AIとの通信に失敗しました。'], 502);
    }

    if ($http_code < 200 || $http_code >= 300) {
        error_log('Gemini API HTTP error: ' . $http_code);
        error_log('Gemini API response: ' . $response);

        sendJson(false, ['error' => 'AI APIからエラーが返されました。'], 502);
    }

    // =============================================
    // 6. Geminiの返答を取得
    // =============================================

    $result = json_decode($response, true);

    $ai_message = '';

    if (!empty($result['candidates'][0]['content']['parts'])) {

        foreach ($result['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $ai_message .= $part['text'];
            }
        }
    }

    if ($ai_message === '') {
        sendJson(false, ['error' => 'AIから有効な返答を取得できませんでした。'], 502);
    }

    // =============================================
    // 7. AIの返答をDBに保存
    // =============================================

    $sql = "
        INSERT INTO ai_chat_messages
            (ai_data_id, role, message, created_at)
        VALUES
            (:ai_data_id, 'model', :message, NOW())
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':ai_data_id' => $ai_data_id,
        ':message' => $ai_message
    ]);

    // 最新のAI返答をai_dataにも保存
    $sql = "
        UPDATE ai_data
        SET ai_result = :ai_result,
            updated_at = NOW()
        WHERE ai_data_id = :ai_data_id
            AND user_id = :user_id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':ai_result' => $ai_message,
        ':ai_data_id' => $ai_data_id,
        ':user_id' => $user_id
    ]);

    // =============================================
    // 8. 画面側へ返答
    // =============================================

    sendJson(true, [
        'ai_data_id' => $ai_data_id,
        'message' => $ai_message
    ]);

} catch (Throwable $e) {

    error_log('Meetory AI listing assist error: ' . $e->getMessage());

    sendJson(false, [
        'error' => 'AI出品アシストの処理中にエラーが発生しました。'
    ], 500);
}
