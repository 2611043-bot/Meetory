
<?php

// 1. APIキー専用ファイルを読み込む
require_once __DIR__ . '/gemini-secret.php';

// APIキーを取得
$api_key = getenv("GEMINI_API_KEY");

// APIキーが取得できているか確認
if (!$api_key) {
    exit('エラー：APIキーを読み込めませんでした。');
}

// 2. 使用するGeminiモデル
$model = 'gemini-3.5-flash';

// 3. APIの接続先
$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/'
    . $model
    . ':generateContent';

// 4. Geminiに送信するデータ
$data = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => '君はモデル何？'
                ]
            ]
        ]
    ]
];

// 5. PHPの配列をJSON形式に変換
$json_data = json_encode($data, JSON_UNESCAPED_UNICODE);

// 6. 通信を準備
$ch = curl_init($api_url);

// 7. 通信の設定
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $api_key
    ],
    CURLOPT_POSTFIELDS => $json_data,
    CURLOPT_TIMEOUT => 60
]);

// 8. Geminiに送信して回答を受け取る
$response = curl_exec($ch);

// 通信エラーを確認
if ($response === false) {
    echo '通信エラー：' . curl_error($ch);
    curl_close($ch);
    exit;
}

// HTTPステータスコードを取得
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// 通信を終了
curl_close($ch);

// 9. 結果を表示
echo '<h2>Gemini API 接続テスト</h2>';
echo '<p>HTTPステータスコード：' . $status_code . '</p>';

$result = json_decode($response, true);

if ($status_code === 200) {
    echo '<h3>Geminiからの回答</h3>';

    $answer = $result['candidates'][0]['content']['parts'][0]['text']
        ?? '回答テキストを取得できませんでした。';

    echo '<pre>' . htmlspecialchars(
        $answer,
        ENT_QUOTES,
        'UTF-8'
    ) . '</pre>';
} else {
    echo '<h3>APIエラーの内容</h3>';
    echo '<pre>' . htmlspecialchars(
        $response,
        ENT_QUOTES,
        'UTF-8'
    ) . '</pre>';
}
