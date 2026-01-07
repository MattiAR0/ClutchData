<?php
/**
 * Debug script for match 267
 */
require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;
use App\Models\PlayerStatsModel;

$db = Database::getInstance()->getConnection();
$matchId = 267;

echo "=== Debug Match $matchId ===\n\n";

// Get match info
$stmt = $db->prepare("SELECT id, team1_name, team2_name, game_type, match_status, match_url, match_details FROM matches WHERE id = ?");
$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    echo "ERROR: Match not found!\n";
    exit(1);
}

echo "Match: {$match['team1_name']} vs {$match['team2_name']}\n";
echo "Game Type: {$match['game_type']}\n";
echo "Status: {$match['match_status']}\n";
echo "URL: {$match['match_url']}\n\n";

// Check match_details JSON
if (!empty($match['match_details'])) {
    $details = json_decode($match['match_details'], true);
    echo "Match details has:\n";
    echo "  - maps: " . count($details['maps'] ?? []) . "\n";
    echo "  - players: " . count($details['players'] ?? []) . "\n";
    echo "  - players_by_map: " . count($details['players_by_map'] ?? []) . "\n";

    if (!empty($details['maps'])) {
        echo "\nMaps found in match_details:\n";
        foreach ($details['maps'] as $map) {
            echo "  - {$map['name']}: {$map['score1']} - {$map['score2']}\n";
        }
    }

    if (!empty($details['players_by_map'])) {
        echo "\nPlayers by map in match_details:\n";
        foreach ($details['players_by_map'] as $mapName => $players) {
            echo "  - $mapName: " . count($players) . " players\n";
        }
    }
} else {
    echo "No match_details stored\n";
}

// Check player_stats table
$playerStats = new PlayerStatsModel($db);
$availableMaps = $playerStats->getAvailableMaps($matchId);
echo "\nMaps in player_stats table: " . count($availableMaps) . "\n";
foreach ($availableMaps as $map) {
    echo "  - '$map'\n";
}

$stats = $playerStats->getStatsByMatch($matchId);
echo "\nTotal player_stats rows: " . count($stats) . "\n";
if (!empty($stats)) {
    echo "First 3 rows:\n";
    foreach (array_slice($stats, 0, 3) as $s) {
        echo "  - {$s['player_name']} | map: '{$s['map_name']}' | source: {$s['data_source']}\n";
    }
}

echo "\n=== End Debug ===\n";
