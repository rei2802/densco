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

// Read message from chatbot.js
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

// Densco chatbot instructions
$prompt = "You are the official Densco Shop Assistant.

Densco is an online medical supplies and equipment shop.

Your responsibilities:
- Answer customer questions about products and services.
- Explain general product information.
- Help with delivery, pickup, payments, orders, returns, and warranties.
- Be friendly, professional, and concise.
- Never invent products, prices, stock quantities, policies, or contact information.
- You do not have live database access unless product information is explicitly provided.
- If you do not know a Densco-specific fact, say so honestly.
- Do not diagnose medical conditions or prescribe treatments.
- Recommend contacting Densco staff when a question requires human assistance.

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

// Prepare the HTTP request
$options = [
    "http" => [
        "method" => "POST",
        "header" =>
            "Content-Type: application/json\r\n" .
            "x-goog-api-key: " . $GEMINI_API_KEY . "\r\n",
        "content" => $payload,
        "timeout" => 30,
        "ignore_errors" => true
    ]
];

$context = stream_context_create($options);

// Send request to Gemini
$response = @file_get_contents($url, false, $context);

// Get HTTP status code
$status = 0;

if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
            $status = (int) $matches[1];
        }
    }
}

// Handle connection failures
if ($response === false) {
    error_log("Densco chatbot: Could not connect to Gemini.");

    respond([
        "success" => false,
        "error" => "Could not connect to the AI service."
    ], 502);
}

// Decode Gemini response
$result = json_decode($response, true);

// Handle API errors
if ($status < 200 || $status >= 300) {
    error_log(
        "Densco Gemini HTTP " . $status . ": " . $response
    );

    respond([
        "success" => false,
        "error" => "The AI service returned an error. Please try again."
    ], 502);
}

// Extract the AI answer
$answer =
    $result["candidates"][0]["content"]["parts"][0]["text"] ?? "";

if (trim($answer) === "") {
    error_log("Densco chatbot: Gemini returned no answer.");

    respond([
        "success" => false,
        "error" => "The AI service returned no answer."
    ], 502);
}

// Return successful response
respond([
    "success" => true,
    "message" => $answer
]);

?>
