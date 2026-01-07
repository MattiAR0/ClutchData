<?php
/**
 * Scheduled Task: Update Matches and Player Stats
 * 
 * Run this script periodically (e.g., every 30 minutes) via:
 * - Windows Task Scheduler
 * - Cron job (Linux)
 * - Manual execution: php cron/update_matches.php
 * 
 * What it does:
 * 1. Scrapes new/upcoming matches from Liquipedia
 * 2. Updates completed matches with detailed stats (per-map data)
 * 3. Respects rate limits to avoid blocking
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\Database;
use App\Classes\ValorantScraper;
use App\Classes\Cs2Scraper;
use App\Classes\LolScraper;
use App\Models\PlayerStatsModel;
use App\Models\MatchModel;

// Configuration
$config = [
    'max_matches_to_update' => 10,     // Max matches to update per run
    'delay_between_requests' => 2,      // Seconds between requests (rate limiting)
    'update_completed_only' => true,    // Only update completed matches
    'force_update_without_maps' => true // Update matches that don't have per-map data
];

echo "=== ClutchData Scheduled Update ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$db = Database::getInstance()->getConnection();
$matchModel = new MatchModel($db);
$statsModel = new PlayerStatsModel($db);

// Step 1: Scrape new matches
echo "[1/3] Scraping new matches from Liquipedia...\n";

$scrapers = [
    'valorant' => new ValorantScraper(),
    'cs2' => new Cs2Scraper(),
    'lol' => new LolScraper()
];

$totalNew = 0;
foreach ($scrapers as $game => $scraper) {
    echo "  - $game: ";
    try {
        $matches = $scraper->scrapeMatches();
        $newCount = 0;
        foreach ($matches as $match) {
            if ($matchModel->saveMatch($match)) {
                $newCount++;
            }
        }
        echo "$newCount new matches\n";
        $totalNew += $newCount;
        sleep($config['delay_between_requests']);
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
echo "  Total new matches: $totalNew\n\n";

// Step 2: Update completed matches without per-map data
echo "[2/3] Updating completed matches with per-map stats...\n";

if ($config['force_update_without_maps']) {
    // Find completed matches that only have 'overall' stats (no per-map)
    $sql = "SELECT DISTINCT m.id, m.game_type, m.match_url, m.team1_name, m.team2_name
            FROM matches m
            LEFT JOIN player_stats ps ON m.id = ps.match_id AND ps.map_name != 'overall'
            WHERE m.match_status = 'completed'
            AND m.match_url IS NOT NULL
            AND ps.id IS NULL
            ORDER BY m.match_time DESC
            LIMIT ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$config['max_matches_to_update']]);
    $matchesToUpdate = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  Found " . count($matchesToUpdate) . " matches to update\n";

    foreach ($matchesToUpdate as $match) {
        echo "  - Updating match {$match['id']}: {$match['team1_name']} vs {$match['team2_name']}... ";

        try {
            $scraper = $scrapers[$match['game_type']] ?? null;
            if (!$scraper) {
                echo "SKIP (unknown game type)\n";
                continue;
            }

            $details = $scraper->scrapeMatchDetails($match['match_url']);

            if (!empty($details['players_by_map'])) {
                // Save per-map stats
                foreach ($details['players_by_map'] as $mapName => $players) {
                    foreach ($players as &$player) {
                        $player['data_source'] = 'liquipedia';
                    }
                    $statsModel->saveStats($match['id'], $players, $mapName);
                }
                echo "OK (" . count($details['players_by_map']) . " maps)\n";
            } else {
                echo "NO MAPS\n";
            }

            sleep($config['delay_between_requests']);

        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

// Step 3: Summary
echo "\n[3/3] Summary\n";
$stmt = $db->query("SELECT COUNT(*) FROM matches WHERE match_status = 'completed'");
$completedCount = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(DISTINCT match_id) FROM player_stats WHERE map_name != 'overall'");
$withMapsCount = $stmt->fetchColumn();

echo "  - Total completed matches: $completedCount\n";
echo "  - Matches with per-map data: $withMapsCount\n";
echo "  - Matches pending update: " . ($completedCount - $withMapsCount) . "\n";

echo "\n=== Update Complete ===\n";
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
