<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\Database;

try {
    $db = Database::getInstance()->getConnection();
    $sql = "ALTER TABLE matches ADD COLUMN match_importance INT DEFAULT 0 AFTER match_status";
    $db->exec($sql);
    echo "Column 'match_importance' added successfully.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), "Duplicate column name")) {
        echo "Column 'match_importance' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
