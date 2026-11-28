<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;

function cloudinary_upload_api(): UploadApi {
    $url = getenv('CLOUDINARY_URL');
    if (!$url) throw new RuntimeException('CLOUDINARY_URL is not configured.');
    Configuration::instance($url)->url->secure = true;
    return new UploadApi();
}
