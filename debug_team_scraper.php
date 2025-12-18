<?php
/**
 * Debug script to test TeamScraper functionality
 * Run: php debug_team_scraper.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Classes\TeamScraper;

echo "=== TeamScraper Debug Test ===\n\n";

$scraper = new TeamScraper();

// Test teams for each game
$testTeams = [
    'valorant' => ['Sentinels', 'Cloud9', 'FNATIC'],
    'lol' => ['T1', 'G2 Esports', 'Cloud9'],
    'cs2' => ['Natus Vincere', 'FaZe Clan', 'Vitality']
];

foreach ($testTeams as $gameType => $teams) {
    echo "=== Testing $gameType ===\n\n";

    foreach ($teams as $teamName) {
        echo "--- Scraping: $teamName ($gameType) ---\n";

        // Build and show URL
        $url = $scraper->buildTeamUrl($teamName, $gameType);
        echo "URL: https://liquipedia.net$url\n";

        // Attempt scrape
        $result = $scraper->scrapeTeam($teamName, $gameType);

        if ($result === null) {
            echo "❌ FAILED: No data returned (fetch failed or empty HTML)\n";
        } else {
            echo "Name: " . ($result['name'] ?? 'N/A') . "\n";
            echo "Region: " . ($result['region'] ?? 'N/A') . "\n";
            echo "Country: " . ($result['country'] ?? 'N/A') . "\n";
            echo "Logo URL: " . ($result['logo_url'] ?? 'N/A') . "\n";
            echo "Description: " . (strlen($result['description'] ?? '') > 0 ? substr($result['description'], 0, 100) . '...' : 'N/A') . "\n";
            echo "Roster count: " . count($result['roster'] ?? []) . "\n";

            if (!empty($result['roster'])) {
                echo "Roster:\n";
                foreach (array_slice($result['roster'], 0, 5) as $player) {
                    echo "  - " . ($player['nickname'] ?? 'Unknown') . " (" . ($player['role'] ?? 'No role') . ")\n";
                }
            }

            echo "Results count: " . count($result['results'] ?? []) . "\n";

            // Check what's missing
            $issues = [];
            if (empty($result['logo_url']))
                $issues[] = 'No logo';
            if (empty($result['description']))
                $issues[] = 'No description';
            if (empty($result['roster']))
                $issues[] = 'No roster';
            if ($result['region'] === 'Other')
                $issues[] = 'Region detection failed';

            if (!empty($issues)) {
                echo "⚠️ Issues: " . implode(', ', $issues) . "\n";
            } else {
                echo "✅ All data found!\n";
            }
        }

        echo "\n";

        // Rate limit
        sleep(2);
    }
}

echo "=== Debug Complete ===\n";
