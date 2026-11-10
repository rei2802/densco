<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/cloudinary.php';

use Cloudinary\Api\Upload\UploadApi;

function json_out($code, $payload) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// Same login check checkout.php relies on — only a logged-in customer may upload a proof.
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    json_out(401, ['success' => false, 'error' => 'Your session expired. Please log out, log in again, then place your order.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['success' => false, 'error' => 'Use POST.']);
}

if (empty($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE) {
    json_out(400, ['success' => false, 'error' => 'No file was uploaded.']);
}

if ($_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    json_out(400, ['success' => false, 'error' => 'The upload failed. Please try again.']);
}

try {
    // Same Cloudinary SDK pattern as staff/inventory.php's handleProductImageUpload(),
    // reusing the Configuration already set up in config/cloudinary.php.
    // resource_type 'auto' is used (rather than inventory.php's default 'image')
    // because cart.php's #proof input also accepts PDF receipts.
    $result = (new UploadApi())->upload(
        $_FILES['proof']['tmp_name'],
        [
            'folder' => 'densco/proofs',
            'resource_type' => 'auto'
        ]
    );

    json_out(200, ['success' => true, 'url' => $result['secure_url']]);
} catch (Exception $e) {
    error_log($e->getMessage());
    json_out(500, ['success' => false, 'error' => 'Could not upload your proof of payment. Please try again.']);
}