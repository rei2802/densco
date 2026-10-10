<?php
// Uses the Railway variable if it exists, otherwise your local key
$apiKey = getenv('GEMINI_API_KEY') ?: 'YOUR_GEMINI_API_KEY';

// If this model name stops working, check AI Studio for a current free one
$model = 'gemini-3.8-flash';