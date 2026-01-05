<?php

/**
 * Migration: Add elo_rating field to teams table
 * Run this script once to add the ELO rating cache
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;

echo "=== Migration: Add elo_rating to teams table ===\n\n";

try {
    $db = Database::getInstance()->getConnection();

    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM teams LIKE 'elo_rating'");
    if ($stmt->fetch()) {
        echo "✓ Column 'elo_rating' already exists. Skipping.\n";
    } else {
        // Add elo_rating column
        $db->exec("ALTER TABLE teams ADD COLUMN elo_rating INT DEFAULT 1500");
        echo "✓ Added 'elo_rating' column to teams table (default: 1500)\n";
    }

    // Check if elo_updated_at column exists
    $stmt = $db->query("SHOW COLUMNS FROM teams LIKE 'elo_updated_at'");
    if ($stmt->fetch()) {
        echo "✓ Column 'elo_updated_at' already exists. Skipping.\n";
    } else {
        $db->exec("ALTER TABLE teams ADD COLUMN elo_updated_at TIMESTAMP NULL DEFAULT NULL");
        echo "✓ Added 'elo_updated_at' column to teams table\n";
    }

    echo "\n=== Migration Complete ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
