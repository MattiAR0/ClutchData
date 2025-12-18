<?php
require 'vendor/autoload.php';

use App\Classes\Database;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM matches WHERE match_details IS NOT NULL AND match_details != '' AND match_details != '[]') as has_details,
            (SELECT COUNT(*) FROM matches WHERE vlr_match_id IS NOT NULL AND vlr_match_id != '') as has_vlr
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Matches with Details: " . $result['has_details'] . "\n";
    echo "Matches with VLR ID: " . $result['has_vlr'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
