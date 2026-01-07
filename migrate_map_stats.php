<?php
/**
 * Migration: Add map_name column to player_stats table
 * 
 * Run this script once to add the column.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;

try {
    $db = Database::getInstance()->getConnection();

    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM player_stats LIKE 'map_name'");
    if ($stmt->rowCount() > 0) {
        echo "Column 'map_name' already exists.\n";
        exit(0);
    }

    // Add the column
    $sql = "ALTER TABLE player_stats ADD COLUMN map_name VARCHAR(50) DEFAULT 'overall' AFTER data_source";
    $db->exec($sql);

    echo "✅ Column 'map_name' added successfully to player_stats table.\n";

    // Add index for better query performance
    $db->exec("CREATE INDEX idx_map_name ON player_stats(map_name)");
    echo "✅ Index 'idx_map_name' created.\n";

} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
