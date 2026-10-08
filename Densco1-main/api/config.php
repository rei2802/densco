<?php
$GEMINI_API_KEY = getenv('GEMINI_API_KEY')
    ?: ($_ENV['GEMINI_API_KEY'] ?? ($_SERVER['GEMINI_API_KEY'] ?? ''));

?>

