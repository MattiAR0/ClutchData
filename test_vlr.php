<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\VlrScraper;

echo "=== Testing VLR.gg Scraper ===\n\n";

try {
    $scraper = new VlrScraper();

    echo "1. Testing scrapeMatches()...\n";
    $matches = $scraper->scrapeMatches();
    $count = count($matches);
    echo "   Found: {$count} matches\n";

    if ($count > 0) {
        echo "\n   First match:\n";
        echo "   Team1: " . $matches[0]['team1'] . "\n";
        echo "   Team2: " . $matches[0]['team2'] . "\n";
        echo "   Tournament: " . $matches[0]['tournament'] . "\n";
        echo "   Status: " . $matches[0]['match_status'] . "\n";
        echo "   Region: " . $matches[0]['region'] . "\n";
    } else {
        echo "   WARNING: No matches found!\n";
    }

    echo "\n2. Testing scrapeResults()...\n";
    $results = $scraper->scrapeResults();
    $rcount = count($results);
    echo "   Found: {$rcount} results\n";

    if ($rcount > 0) {
        echo "\n   First result:\n";
        echo "   Team1: " . $results[0]['team1'] . " (" . $results[0]['team1_score'] . ")\n";
        echo "   Team2: " . $results[0]['team2'] . " (" . $results[0]['team2_score'] . ")\n";
        echo "   Tournament: " . $results[0]['tournament'] . "\n";
    }

    echo "\n=== SUCCESS ===\n";
} catch (Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
