<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Densco Database Connection
|--------------------------------------------------------------------------
| Railway: Uses DATABASE_URL when available.
| XAMPP:   Uses local MySQL settings.
|--------------------------------------------------------------------------
*/

$databaseUrl = getenv('DATABASE_URL');

try {
    if ($databaseUrl) {
        // Railway database connection
        $db = parse_url($databaseUrl);

        $host = $db['host'] ?? 'localhost';
        $port = $db['port'] ?? 3306;
        $user = isset($db['user']) ? urldecode($db['user']) : 'root';
        $pass = isset($db['pass']) ? urldecode($db['pass']) : '';
        $name = isset($db['path'])
            ? ltrim($db['path'], '/')
            : 'densco_db';

    } else {
    // XAMPP local database connection
    $host = 'localhost';
    $port = 3306;
    $user = 'root';
    $pass = '';
    $name = 'densco_db';   // 
}

    // Connect to the database
    $conn = new mysqli(
        $host,
        $user,
        $pass,
        $name,
        (int) $port
    );

    // Set UTF-8 encoding
    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    error_log('Densco database connection failed: ' . $e->getMessage());

    die(
        'Unable to connect to the database. Please check your database '
        . 'configuration and ensure MySQL is running.'
    );
}
?>

