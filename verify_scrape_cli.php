<?php
require __DIR__ . '/vendor/autoload.php';

use App\Classes\ValorantScraper;

echo "Testing Valorant Scraper...\n";
try {
    $scraper = new ValorantScraper();
    $matches = $scraper->scrapeMatches();

    echo "Total Matches Found: " . count($matches) . "\n";
    $scoredMatches = 0;

    foreach ($matches as $m) {
        if (isset($m['team1_score']) && $m['team1_score'] !== null) {
            $scoredMatches++;
            echo "MATCH: {$m['team1']} vs {$m['team2']} | Result: {$m['team1_score']}-{$m['team2_score']} | Status: {$m['match_status']}\n";
            echo "URL: {$m['match_url']}\n";
            echo "---------------------------------------------------\n";
        }
    }

    echo "Matches with scores: $scoredMatches\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
