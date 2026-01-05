<?php

/**
 * Test script for the AI Prediction System
 * Run this to verify ELO + Head-to-Head predictions work correctly
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\MatchPredictor;
use App\Classes\Database;

echo "=== AI Prediction System Test ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    $predictor = new MatchPredictor($db);

    // Check if we have matches
    $stmt = $db->query("SELECT COUNT(*) as total FROM matches WHERE match_status = 'completed'");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Completed matches in database: $count\n\n";

    if ($count == 0) {
        echo "⚠ No completed matches found. Run sync first to get match data.\n";
        exit(0);
    }

    // Get some sample teams for testing
    $stmt = $db->query("
        SELECT DISTINCT team1_name as name, game_type 
        FROM matches 
        WHERE match_status = 'completed' 
        LIMIT 5
    ");
    $teams1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("
        SELECT DISTINCT team2_name as name, game_type 
        FROM matches 
        WHERE match_status = 'completed' 
        LIMIT 5
    ");
    $teams2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "--- Testing ELO Ratings ---\n";
    foreach ($teams1 as $team) {
        $elo = $predictor->getTeamElo($team['name'], $team['game_type']);
        echo sprintf("  %s (%s): ELO %d\n", $team['name'], $team['game_type'], $elo);
    }

    echo "\n--- Testing Predictions ---\n";
    // Test some predictions
    for ($i = 0; $i < min(3, count($teams1)); $i++) {
        if (isset($teams2[$i])) {
            $t1 = $teams1[$i];
            $t2 = $teams2[$i];
            $gameType = $t1['game_type'];

            $prediction = $predictor->predictMatch($t1['name'], $t2['name'], $gameType);

            echo sprintf(
                "  %s vs %s (%s): %.1f%% win probability for %s\n",
                $t1['name'],
                $t2['name'],
                $gameType,
                $prediction,
                $t1['name']
            );
        }
    }

    echo "\n--- Recalculating All Ratings ---\n";
    $stats = $predictor->recalculateAllRatings();
    echo "Updated {$stats['updated']} team ratings in database\n";

    // Show top 5 teams by ELO
    echo "\n--- Top Teams by ELO ---\n";
    usort($stats['teams'], fn($a, $b) => $b['elo'] - $a['elo']);
    $top = array_slice($stats['teams'], 0, 10);
    foreach ($top as $team) {
        echo sprintf("  %s (%s): %d\n", $team['name'], $team['game'], $team['elo']);
    }

    echo "\n--- Updating Match Predictions ---\n";
    $updated = $predictor->updateAllMatchPredictions();
    echo "Updated predictions for $updated upcoming matches\n";

    echo "\n=== Test Complete ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
