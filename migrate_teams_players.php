<?php

/**
 * Migration script to add teams and players tables
 * Run this once to create the new tables
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
} catch (Exception $e) {
    // Continue if .env fails
}

try {
    $db = Database::getInstance()->getConnection();

    // Create teams table
    $db->exec("
        CREATE TABLE IF NOT EXISTS teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            game_type ENUM('valorant', 'lol', 'cs2') NOT NULL,
            region VARCHAR(50) DEFAULT 'Other',
            country VARCHAR(100) DEFAULT NULL,
            logo_url VARCHAR(512) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            liquipedia_url VARCHAR(512) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_team (name, game_type),
            INDEX idx_game_type (game_type),
            INDEX idx_region (region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created teams table\n";

    // Create players table
    $db->exec("
        CREATE TABLE IF NOT EXISTS players (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nickname VARCHAR(100) NOT NULL,
            real_name VARCHAR(255) DEFAULT NULL,
            team_id INT DEFAULT NULL,
            game_type ENUM('valorant', 'lol', 'cs2') NOT NULL,
            country VARCHAR(100) DEFAULT NULL,
            role VARCHAR(50) DEFAULT NULL,
            photo_url VARCHAR(512) DEFAULT NULL,
            birthdate DATE DEFAULT NULL,
            description TEXT DEFAULT NULL,
            liquipedia_url VARCHAR(512) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
            UNIQUE KEY unique_player (nickname, game_type),
            INDEX idx_team (team_id),
            INDEX idx_game (game_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✓ Created players table\n";
    echo "\n✓ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
