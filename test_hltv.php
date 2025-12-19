<?php
/**
 * Test script for HltvScraper
 * Run this to verify HLTV connectivity and scraping
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\HltvScraper;

echo "=== HLTV Scraper Test ===\n\n";

try {
    $scraper = new HltvScraper();

    echo "Game Type: " . $scraper->getGameType() . "\n\n";

    echo "--- Testing scrapeResults() ---\n";
    echo "Fetching recent results from HLTV (this may take a few seconds due to rate limiting)...\n\n";

    $results = $scraper->scrapeResults();

    if (empty($results)) {
        echo "⚠ No results returned. HLTV may be blocking the request or the HTML structure changed.\n";
        echo "This is expected if HLTV has Cloudflare protection active.\n";
    } else {
        echo "✓ Found " . count($results) . " completed matches:\n\n";

        $shown = 0;
        foreach ($results as $match) {
            if ($shown >= 5)
                break;

            echo sprintf(
                "  %s vs %s [%s - %s] - %s (HLTV ID: %s)\n",
                $match['team1'],
                $match['team2'],
                $match['team1_score'] ?? '?',
                $match['team2_score'] ?? '?',
                $match['tournament'],
                $match['hltv_match_id'] ?? 'N/A'
            );
            $shown++;
        }

        if (count($results) > 5) {
            echo "  ... and " . (count($results) - 5) . " more\n";
        }
    }

    echo "\n=== Test Complete ===\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
