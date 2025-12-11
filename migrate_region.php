<?php
require __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;

try {
    $db = Database::getInstance()->getConnection();
    // Check if column exists to avoid error if run multiple times
    $check = $db->query("SHOW COLUMNS FROM matches LIKE 'match_region'");
    if ($check->rowCount() == 0) {
        $db->exec("ALTER TABLE matches ADD COLUMN match_region VARCHAR(50) DEFAULT 'Unknown'");
        echo "Column match_region added successfully.\n";
    } else {
        echo "Column match_region already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
