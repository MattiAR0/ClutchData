<?php
/**
 * Test team display by fetching teams from DB and scraping one
 */

require __DIR__ . '/vendor/autoload.php';

use App\Classes\TeamScraper;
use App\Models\TeamModel;

echo "=== Team DB & Display Test ===\n\n";

$teamModel = new TeamModel();

// Check teams in DB
echo "1. Teams currently in database:\n";
$allTeams = $teamModel->getAllTeams();

if (empty($allTeams)) {
    echo "   No teams in database yet.\n";
} else {
    foreach (array_slice($allTeams, 0, 10) as $team) {
        echo "   - {$team['name']} ({$team['game_type']}) - Region: {$team['region']}\n";
    }
    echo "   Total: " . count($allTeams) . " teams\n";
}

echo "\n2. Teams from matches table:\n";
$matchTeams = $teamModel->getTeamsFromMatches();
echo "   Found " . count($matchTeams) . " unique team names from matches\n";
if (!empty($matchTeams)) {
    foreach (array_slice($matchTeams, 0, 10) as $team) {
        echo "   - {$team['name']} ({$team['game_type']})\n";
    }
}

echo "\n3. Testing scrape + save workflow:\n";
$scraper = new TeamScraper();

// Scrape a team
$teamData = $scraper->scrapeTeam('Fnatic', 'valorant');

if ($teamData) {
    echo "   Scraped: {$teamData['name']} ({$teamData['game_type']})\n";
    echo "   Logo: " . ($teamData['logo_url'] ? 'YES' : 'NO') . "\n";
    echo "   Region: {$teamData['region']}\n";
    echo "   Roster: " . count($teamData['roster']) . " players\n";

    // Try to save
    $teamId = $teamModel->saveTeam([
        'name' => $teamData['name'],
        'game_type' => $teamData['game_type'],
        'region' => $teamData['region'],
        'country' => $teamData['country'],
        'logo_url' => $teamData['logo_url'],
        'description' => $teamData['description'],
        'liquipedia_url' => $teamData['liquipedia_url']
    ]);

    echo "   Saved with ID: $teamId\n";

    // Verify it's saved
    $savedTeam = $teamModel->getTeamById($teamId);
    if ($savedTeam) {
        echo "   ✅ Retrieved from DB: {$savedTeam['name']}\n";
        echo "   Logo URL: " . substr($savedTeam['logo_url'] ?? 'NULL', 0, 60) . "...\n";
    }
} else {
    echo "   ❌ Failed to scrape\n";
}

echo "\n=== Test Complete ===\n";
