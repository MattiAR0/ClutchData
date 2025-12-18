<?php
require __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;

try {
    $db = Database::getInstance()->getConnection();

    $columns = [
        'team1_score' => "INT DEFAULT NULL",
        'team2_score' => "INT DEFAULT NULL",
        'match_status' => "ENUM('upcoming', 'live', 'completed') DEFAULT 'upcoming'",
        'match_url' => "VARCHAR(512) DEFAULT NULL",
        'bo_type' => "VARCHAR(20) DEFAULT NULL",
        'match_importance' => "INT DEFAULT 0",
        'vlr_match_id' => "VARCHAR(50) DEFAULT NULL"
    ];

    foreach ($columns as $colName => $colDef) {
        $check = $db->query("SHOW COLUMNS FROM matches LIKE '$colName'");
        if ($check->rowCount() == 0) {
            $db->exec("ALTER TABLE matches ADD COLUMN $colName $colDef");
            echo "Column $colName added successfully.\n";
        } else {
            echo "Column $colName already exists.\n";
        }
    }

    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
