<?php

require 'config/cloudinary.php';

use Cloudinary\Api\Upload\UploadApi;

$result = (new UploadApi())->upload(
    'sample.jpg',
    [
        'folder' => 'densco/products'
    ]
);

echo "<h2>Upload Successful!</h2>";

echo "<p>Image URL:</p>";
echo "<a href='{$result['secure_url']}' target='_blank'>
        {$result['secure_url']}
      </a>";

echo "<br><br>";

echo "<img src='{$result['secure_url']}'
          width='300'>";