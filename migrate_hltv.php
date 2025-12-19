<?php
/**
 * Migration script to add HLTV support
 * Run once to update database schema
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;

echo "=== HLTV Migration Script ===\n\n";

try {
    $pdo = Database::getInstance()->getConnection();

    // Check if hltv_match_id column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM matches LIKE 'hltv_match_id'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        // Add hltv_match_id column
        $pdo->exec("ALTER TABLE matches ADD COLUMN hltv_match_id VARCHAR(50) DEFAULT NULL AFTER vlr_match_id");
        echo "✓ Added 'hltv_match_id' column to matches table\n";
    } else {
        echo "→ Column 'hltv_match_id' already exists, skipping...\n";
    }

    // Update data_source ENUM in player_stats table to include 'hltv'
    $stmt = $pdo->query("SHOW COLUMNS FROM player_stats LIKE 'data_source'");
    $dataSourceCol = $stmt->fetch();

    if ($dataSourceCol) {
        $currentType = $dataSourceCol['Type'];

        if (strpos($currentType, 'hltv') === false) {
            // Modify ENUM to include 'hltv'
            $pdo->exec("ALTER TABLE player_stats MODIFY COLUMN data_source ENUM('liquipedia', 'vlr', 'hltv') DEFAULT 'liquipedia'");
            echo "✓ Updated 'data_source' ENUM to include 'hltv'\n";
        } else {
            echo "→ data_source ENUM already includes 'hltv', skipping...\n";
        }
    }

    // Add rating column to player_stats if not exists (for HLTV Rating 2.0)
    $stmt = $pdo->query("SHOW COLUMNS FROM player_stats LIKE 'rating'");
    $ratingExists = $stmt->fetch();

    if (!$ratingExists) {
        $pdo->exec("ALTER TABLE player_stats ADD COLUMN rating DECIMAL(3,2) DEFAULT NULL AFTER hs_percent");
        echo "✓ Added 'rating' column to player_stats table\n";
    } else {
        echo "→ Column 'rating' already exists, skipping...\n";
    }

    echo "\n=== Migration completed successfully! ===\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
