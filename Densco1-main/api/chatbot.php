<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

if (!function_exists('fail')) {
    function fail($code, $msg) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Use POST');
}

$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$message = trim($input['message'] ?? '');
$context = trim($input['context'] ?? '');
$history = is_array($input['history'] ?? null) ? $input['history'] : [];

if ($message === '') {
    fail(400, 'Message is empty');
}

$systemText =
"You are the Densco Assistant, the official virtual assistant of Densco, a seller of hospital and medical equipment in the Philippines.\n" .
"Language:\n" .
"- Always reply in the same language as the customer's latest message (for example English, Filipino/Tagalog, Cebuano, Ilocano, Spanish, Chinese, Japanese, Korean, Arabic, Hindi). Understand any language the customer uses.\n" .
"- If the customer mixes languages (such as Taglish), reply in the same natural mix.\n" .
"- Keep product names, prices (PHP) and the phrase 'talk to staff' exactly as they appear in the business data.\n" .
"Tone and style:\n" .
"- Be professional, courteous and polite, with a warm and respectful tone suitable for business customers such as clinics and hospitals.\n" .
"- Keep answers short, clear and well organized (2 to 4 sentences). Offer further help at the end when appropriate.\n" .
"- Speak as Densco, using 'we' and 'our'.\n" .
"Rules:\n" .
"- For questions about Densco products, stock, prices, delivery, payment, hours or location, answer ONLY from the BUSINESS DATA below.\n" .
"- If the answer is not in the business data, do not guess. Politely say you are unable to confirm it and offer to connect the customer with a Densco team member. Tell them to type 'talk to staff'.\n" .
"- Never invent prices, stock numbers, specs or policies.\n" .
"- Do not give medical advice. For unrelated questions, politely explain that you can only assist with Densco products and services.\n" .
"- Use plain text only, no markdown, no emojis.\n\n" .
"BUSINESS DATA:\n" . ($context !== '' ? $context : '(none available)');

$contents = [];
foreach (array_slice($history, -12) as $h) {
    $role = (($h['role'] ?? '') === 'model') ? 'model' : 'user';
    $text = trim((string)($h['text'] ?? ''));
    if ($text === '') continue;
    $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$payload = [
    'system_instruction' => ['parts' => [['text' => $systemText]]],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.3,
        'maxOutputTokens' => 1000   // non-English text uses more tokens
    ]
];

$models = [$model, 'gemini-3-flash-preview', 'gemini-2.5-flash-lite'];

set_time_limit(60);

$reply = null;
$lastError = 'No response from Gemini';

foreach ($models as $m) {
    for ($try = 1; $try <= 2; $try++) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
            // , CURLOPT_SSL_VERIFYPEER => false  // local XAMPP only
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $lastError = 'Connection failed: ' . curl_error($ch);
            curl_close($ch);
            sleep(1);
            continue;
        }
        curl_close($ch);

        $data = json_decode($response, true);
        $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($reply !== null) break 2;

        $lastError = $data['error']['message'] ?? $lastError;

        if (!in_array($status, [429, 500, 503])) break;
        sleep(1);
    }
}

if ($reply === null) {
    fail(500, $lastError);
}

echo json_encode(['success' => true, 'message' => $reply], JSON_UNESCAPED_UNICODE);