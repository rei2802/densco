<?php

header("Content-Type: application/json");

require_once "config.php";

/*
 * Get the message sent by chatbot.js
 */
$input = json_decode(file_get_contents("php://input"), true);

$message = isset($input["message"]) ? trim($input["message"]) : "";

if ($message === "") {
    echo json_encode([
        "success" => false,
        "error" => "No message received."
    ]);
    exit;
}

/*
 * Send the user's message to Gemini
 */
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent";
$data = [
    "contents" => [
        [
            "parts" => [
    [
        "text" => "You are the official Densco Shop Assistant.

Densco is an online medical supplies and equipment shop.

You are speaking directly to customers visiting the Densco website.

Your job is to:
- Answer questions about Densco products and services.
- Help customers understand products.
- Answer questions about delivery, pickup, payment, orders, returns, and warranties.
- Be friendly, professional, and concise.
- Never pretend to be a different company.
- Never tell the customer to provide information about another shop when they are asking about Densco.
- If you do not know something about Densco, say that you don't have that information rather than making it up.
- Do not invent products, prices, stock quantities, policies, or contact information.

Customer's message:
" . $message
    ]
]
        ]
    ]
];

$json = json_encode($data);

$headers = [
    "Content-Type: application/json",
    "x-goog-api-key: " . $GEMINI_API_KEY
];

$context = stream_context_create([
    "http" => [
        "method" => "POST",
        "header" => implode("\r\n", $headers),
        "content" => $json,
        "ignore_errors" => true
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo json_encode([
        "success" => false,
        "error" => "PHP could not connect to Gemini."
    ]);
    exit;
}

/*
 * Read Gemini's response
 */
$result = json_decode($response, true);

if (isset($result["candidates"][0]["content"]["parts"][0]["text"])) {

    echo json_encode([
        "success" => true,
        "message" => $result["candidates"][0]["content"]["parts"][0]["text"]
    ]);

} else {

    echo json_encode([
        "success" => false,
        "error" => "Gemini did not return a response.",
        "raw_response" => $response
    ]);
}

?>