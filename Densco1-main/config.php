<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Densco Database Connection
|--------------------------------------------------------------------------
| Railway:
|   Uses DATABASE_URL if it exists.
|
| XAMPP / Local:
|   Falls back to localhost, root, no password, densco_db.
|--------------------------------------------------------------------------
*/

$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    // Railway DATABASE_URL format:
    // mysql://username:password@host:port/database

    $db = parse_url($databaseUrl);

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = $db['port'] ?? 3306;
    $user = isset($db['user']) ? urldecode($db['user']) : 'root';
    $pass = isset($db['pass']) ? urldecode($db['pass']) : '';
    $name = isset($db['path']) ? ltrim($db['path'], '/') : 'densco_db';

} else {
    // XAMPP / Local MySQL settings
$host = "localhost";
$port = 3306;
$user = "root";
$pass = "";
$name = "densco_db";
}

// Connect to MySQL
$conn = new mysqli(
    $host,
    $user,
    $pass,
    $name,
    (int)$port
);

// Set UTF-8
$conn->set_charset("utf8mb4");
?>