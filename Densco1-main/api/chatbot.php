```php
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/config.php";

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond([
        "success" => false,
        "error" => "Method not allowed."
    ], 405);
}

// Check API key
if (empty($GEMINI_API_KEY)) {
    error_log("Densco chatbot: GEMINI_API_KEY is missing.");

    respond([
        "success" => false,
        "error" => "AI service is not configured."
    ], 500);
}

// Read the customer's message
$input = json_decode(file_get_contents("php://input"), true);
$message = trim($input["message"] ?? "");

if ($message === "") {
    respond([
        "success" => false,
        "error" => "Please enter a message."
    ], 400);
}

// Gemini API endpoint
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent";

// Instructions for Densco's AI assistant
$prompt = "You are the official Densco Shop Assistant.

Densco is an online medical supplies and equipment shop.

Your responsibilities:
- Answer customer questions about products and services.
- Explain general product information.
- Help with delivery, pickup, payment, orders, returns, and warranties.
- Be friendly, professional, and concise.
- Never pretend to represent another company.
- Never invent products, prices, stock quantities, policies, or contact information.
- You do not have live database access unless product information is explicitly provided.
- If you do not know a Densco-specific fact, say so honestly.
- Do not diagnose medical conditions or prescribe treatments.
- For questions requiring staff assistance, politely recommend contacting the Densco team.

Customer's message:
" . $message;

$payload = json_encode([
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.3,
        "maxOutputTokens" => 300
    ]
]);

if ($payload === false) {
    respond([
        "success" => false,
        "error" => "Could not prepare the AI request."
    ], 500);
}

// Check whether PHP cURL is available
if (!function_exists("curl_init")) {
    error_log("Densco chatbot: PHP cURL extension is unavailable.");

    respond([
        "success" => false,
        "error" => "AI service is temporarily unavailable."
    ], 500);
}

// Send request to Gemini
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "x-goog-api-key: " . $GEMINI_API_KEY
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Handle connection errors
if ($response === false) {
    error_log("Densco Gemini connection error: " . $curlError);

    respond([
        "success" => false,
        "error" => "Could not connect to the AI service."
    ], 502);
}

// Decode Gemini's response
$result = json_decode($response, true);

// Handle Gemini HTTP errors
if ($httpCode < 200 || $httpCode >= 300) {
    error_log(
        "Densco Gemini HTTP " . $httpCode . ": " . $response
    );

    respond([
        "success" => false,
        "error" => "The AI service returned an error. Please try again."
    ], 502);
}

// Extract AI response
$answer = $result["candidates"][0]["content"]["parts"][0]["text"] ?? "";

if (trim($answer) === "") {
    error_log("Densco Gemini: no answer returned.");

    respond([
        "success" => false,
        "error" => "The AI service returned no answer."
    ], 502);
}

// Return answer to chatbot.js
respond([
    "success" => true,
    "message" => $answer
]);

?>
```
