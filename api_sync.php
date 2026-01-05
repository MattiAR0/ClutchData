<?php

/**
 * Background Sync API Endpoint
 * Called via AJAX to sync data without blocking page load
 * 
 * Returns JSON with sync status
 */

// Prevent timeout and allow background execution
ignore_user_abort(true);
set_time_limit(120);

header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;
use App\Classes\MatchPredictor;
use App\Classes\Database;
use App\Models\MatchModel;

// Minimum minutes between syncs (avoid hammering Liquipedia)
const MIN_SYNC_INTERVAL = 15;

$response = [
    'success' => false,
    'message' => '',
    'synced' => 0,
    'skipped' => false
];

try {
    $db = Database::getInstance()->getConnection();
    $matchModel = new MatchModel();

    // Check last sync time
    $stmt = $db->query("SELECT MAX(created_at) as last_sync FROM matches");
    $lastSync = $stmt->fetch(PDO::FETCH_ASSOC)['last_sync'];

    if ($lastSync) {
        $lastSyncTime = strtotime($lastSync);
        $minutesSinceSync = (time() - $lastSyncTime) / 60;

        if ($minutesSinceSync < MIN_SYNC_INTERVAL) {
            $response['success'] = true;
            $response['skipped'] = true;
            $response['message'] = "Data is fresh (synced " . round($minutesSinceSync) . " min ago)";
            echo json_encode($response);
            exit;
        }
    }

    // Perform lightweight sync (only new matches, no full refresh)
    $scrapers = [
        new ValorantScraper(),
        new LolScraper(),
        new Cs2Scraper()
    ];

    $totalNew = 0;

    foreach ($scrapers as $scraper) {
        try {
            $data = $scraper->scrapeMatches();
            if (!empty($data)) {
                // Filter only new matches
                $newMatches = filterNewMatches($data, $db);
                if (!empty($newMatches)) {
                    $matchModel->saveMatches($newMatches);
                    $totalNew += count($newMatches);
                }
            }
        } catch (Exception $e) {
            error_log("Background sync error for " . $scraper->getGameType() . ": " . $e->getMessage());
        }

        // Brief pause between scrapers
        usleep(500000); // 0.5 seconds
    }

    // Update predictions for upcoming matches
    if ($totalNew > 0) {
        try {
            $predictor = new MatchPredictor($db);
            $predictor->updateAllMatchPredictions();
        } catch (Exception $e) {
            error_log("Prediction update error: " . $e->getMessage());
        }
    }

    $response['success'] = true;
    $response['synced'] = $totalNew;
    $response['message'] = $totalNew > 0 ? "Synced $totalNew new matches" : "No new matches found";
} catch (Exception $e) {
    $response['message'] = "Sync error: " . $e->getMessage();
    error_log("Background sync fatal: " . $e->getMessage());
}

echo json_encode($response);

/**
 * Filter out matches that already exist in database
 */
function filterNewMatches(array $matches, PDO $db): array
{
    $new = [];

    $stmt = $db->prepare("
        SELECT 1 FROM matches 
        WHERE LOWER(team1_name) = LOWER(?) 
          AND LOWER(team2_name) = LOWER(?) 
          AND DATE(match_time) = DATE(?)
        LIMIT 1
    ");

    foreach ($matches as $match) {
        $stmt->execute([
            $match['team1'],
            $match['team2'],
            $match['time']
        ]);

        if (!$stmt->fetch()) {
            $new[] = $match;
        }
    }

    return $new;
}
