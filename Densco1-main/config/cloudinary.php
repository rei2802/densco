<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Cloudinary\Configuration\Configuration;

Configuration::instance([
    'cloud' => [
        'cloud_name' => 'cm94cbki',
        'api_key'    => '888343599292784',
        'api_secret' => 'qsoU9sYANAkZCLDiHhOuoaPNzJg'
    ],
    'url' => [
        'secure' => true
    ]
]);


?>