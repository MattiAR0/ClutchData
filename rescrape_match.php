<?php
/**
 * Re-scrape match details from Liquipedia
 * Usage: php rescrape_match.php <match_id>
 */
require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;
use App\Classes\ValorantScraper;
use App\Classes\Cs2Scraper;
use App\Classes\LolScraper;
use App\Models\PlayerStatsModel;

$matchId = $argv[1] ?? null;

if (!$matchId) {
    echo "Usage: php rescrape_match.php <match_id>\n";
    exit(1);
}

$db = Database::getInstance()->getConnection();

// Get match info
$stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    echo "Match not found!\n";
    exit(1);
}

echo "=== Re-scraping Match $matchId ===\n\n";
echo "Match: {$match['team1_name']} vs {$match['team2_name']}\n";
echo "Game: {$match['game_type']}\n";
echo "URL: {$match['match_url']}\n\n";

// Select correct scraper
switch ($match['game_type']) {
    case 'valorant':
        $scraper = new ValorantScraper();
        break;
    case 'cs2':
        $scraper = new Cs2Scraper();
        break;
    case 'lol':
        $scraper = new LolScraper();
        break;
    default:
        echo "Unknown game type: {$match['game_type']}\n";
        exit(1);
}

echo "Scraping from Liquipedia...\n";
$details = $scraper->scrapeMatchDetails($match['match_url']);

if (empty($details)) {
    echo "ERROR: Could not scrape details!\n";
    exit(1);
}

echo "Scraped:\n";
echo "  - maps: " . count($details['maps'] ?? []) . "\n";
echo "  - players: " . count($details['players'] ?? []) . "\n";
echo "  - players_by_map: " . count($details['players_by_map'] ?? []) . "\n";

if (!empty($details['maps'])) {
    echo "\nMaps found:\n";
    foreach ($details['maps'] as $map) {
        echo "  - {$map['name']}: {$map['score1']} - {$map['score2']}\n";
    }
}

if (!empty($details['players_by_map'])) {
    echo "\nPlayers by map:\n";
    foreach ($details['players_by_map'] as $mapName => $players) {
        echo "  - $mapName: " . count($players) . " players\n";
    }
}

// Save to database
echo "\nSaving to database...\n";

// Update match_details
$stmt = $db->prepare("UPDATE matches SET match_details = ? WHERE id = ?");
$stmt->execute([json_encode($details), $matchId]);
echo "✓ Updated match_details\n";

// Save player stats
$statsModel = new PlayerStatsModel($db);

// Delete old stats
$statsModel->deleteByMatch($matchId);
echo "✓ Deleted old player stats\n";

// Save overall
if (!empty($details['players'])) {
    foreach ($details['players'] as &$player) {
        $player['data_source'] = 'liquipedia';
    }
    $statsModel->saveStats($matchId, $details['players'], 'overall');
    echo "✓ Saved overall stats (" . count($details['players']) . " players)\n";
}

// Save per-map
if (!empty($details['players_by_map'])) {
    foreach ($details['players_by_map'] as $mapName => $mapPlayers) {
        foreach ($mapPlayers as &$player) {
            $player['data_source'] = 'liquipedia';
        }
        $statsModel->saveStats($matchId, $mapPlayers, $mapName);
        echo "✓ Saved $mapName stats (" . count($mapPlayers) . " players)\n";
    }
}

echo "\n=== Done! Refresh the page to see maps ===\n";
