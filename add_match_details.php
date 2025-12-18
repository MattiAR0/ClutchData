<?php
require 'vendor/autoload.php';

use App\Classes\Database;

try {
    $db = Database::getInstance()->getConnection();
    echo "Adding match_details column...\n";
    $db->exec("ALTER TABLE matches ADD COLUMN match_details JSON DEFAULT NULL AFTER vlr_match_id");
    echo "Column added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
