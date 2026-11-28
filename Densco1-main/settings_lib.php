<?php

const BUSINESS_SETTINGS_DEFAULTS = [
    'address' => 'Quezon City, Metro Manila, Philippines',
    'hours' => 'Monday to Saturday, 8:00 AM to 6:00 PM',
    'contact' => 'Viber, Messenger and SMS',
    'viber' => '',
    'facebook' => '',
    'mapUrl' => '',
    'deliveryFee' => 120,
    'lowStock' => 10,
];

function business_settings(mysqli $db, ?bool &$saved = null): array {
    $saved = false;
    try {
        $row = $db->query('SELECT settings FROM business_settings WHERE id = 1')->fetch_assoc();
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1146) throw $error;
        return BUSINESS_SETTINGS_DEFAULTS;
    }
    if (!$row) return BUSINESS_SETTINGS_DEFAULTS;
    $saved = true;
    return array_replace(BUSINESS_SETTINGS_DEFAULTS, json_decode($row['settings'], true, flags: JSON_THROW_ON_ERROR));
}

function save_business_settings(mysqli $db, array $settings): void {
    $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $sql = 'INSERT INTO business_settings (id, settings) VALUES (1, ?) ON DUPLICATE KEY UPDATE settings = ?';
    try {
        $db->execute_query($sql, [$json, $json]);
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1146) throw $error;
        $db->query('CREATE TABLE IF NOT EXISTS business_settings (
            id TINYINT UNSIGNED PRIMARY KEY, settings JSON NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $db->execute_query($sql, [$json, $json]);
    }
}
