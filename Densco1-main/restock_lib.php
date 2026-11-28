<?php

function ensure_restocks_table(mysqli $db): void {
    // The column names match the previous Densco schema and existing reports.
    $db->query('CREATE TABLE IF NOT EXISTS restocks (
        restock_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        supplier_name VARCHAR(150) NOT NULL,
        quantity INT NOT NULL,
        restock_date DATE NOT NULL,
        staff_id INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX restocks_date (restock_date),
        INDEX restocks_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function record_inventory_restock(mysqli $db, int $productId, int $quantity, string $supplier, int $staffId): void {
    $date = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    $db->execute_query(
        'INSERT INTO restocks (product_id, supplier_name, quantity, restock_date, staff_id) VALUES (?, ?, ?, ?, ?)',
        [$productId, $supplier, $quantity, $date, $staffId]
    );
}
