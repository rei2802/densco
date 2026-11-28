<?php

require_once __DIR__ . '/../settings_lib.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function settings_response(int $status, array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    settings_response(405, ['error' => 'Method not allowed.']);
}

if ($method === 'POST') {
    session_start(['read_and_close' => true]);
    $staffId = (int) ($_SESSION['staff_id'] ?? 0);
    if (!$staffId) settings_response(401, ['error' => 'Sign in again to save settings.']);
    if (($_SESSION['staff_role'] ?? null) !== 'owner') settings_response(403, ['error' => 'Only the business owner can save settings.']);
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') settings_response(415, ['error' => 'Send settings as JSON.']);
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) settings_response(400, ['error' => 'Settings could not be read.']);
    $settings = [];
    foreach (['address', 'hours', 'contact', 'viber', 'facebook', 'mapUrl'] as $key) {
        if (!is_string($input[$key] ?? null) || strlen($input[$key]) > 1024) {
            settings_response(422, ['error' => 'Check the business information fields.']);
        }
        $settings[$key] = trim($input[$key]);
    }
    foreach (['facebook', 'mapUrl'] as $key) {
        $url = $settings[$key];
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url))) {
            settings_response(422, ['error' => 'Enter a valid HTTP or HTTPS link.']);
        }
    }
    $fee = $input['deliveryFee'] ?? null;
    $low = $input['lowStock'] ?? null;
    if ((!is_int($fee) && !is_float($fee)) || $fee < 0 || $fee > 100000 || round($fee, 2) != $fee
        || !is_int($low) || $low < 0 || $low > 100000) {
        settings_response(422, ['error' => 'Enter a valid delivery fee and whole-number stock threshold.']);
    }
    $settings['deliveryFee'] = $fee;
    $settings['lowStock'] = $low;
}

try {
    $throwDatabaseConnectionErrors = true;
    require_once __DIR__ . '/../config.php';
    if ($method === 'POST') {
        $role = $conn->execute_query('SELECT role FROM staff WHERE staff_id = ?', [$staffId])->fetch_assoc();
        if (($role['role'] ?? null) !== 'owner') settings_response(403, ['error' => 'Only the business owner can save settings.']);
        save_business_settings($conn, $settings);
        settings_response(200, ['settings' => $settings]);
    }
    $settings = business_settings($conn, $saved);
    if (isset($_GET['script'])) {
        header('Content-Type: text/javascript; charset=utf-8');
        echo 'window.DHP_SETTINGS = ' . json_encode($settings, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) . ';';
        echo 'window.DHP_SETTINGS_SAVED = ' . ($saved ? 'true' : 'false') . ';';
        exit;
    }
    settings_response(200, ['settings' => $settings]);
} catch (Throwable $error) {
    error_log('Business settings: ' . $error->getMessage());
    settings_response(503, ['error' => 'Business settings are temporarily unavailable.']);
}
