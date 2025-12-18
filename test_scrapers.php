<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== Testing Scrapers ===\n\n";

echo "1. Testing ValorantScraper...\n";
try {
    $scraper = new ValorantScraper();
    $matches = $scraper->scrapeMatches();
    echo "   Found: " . count($matches) . " matches\n";
    if (count($matches) > 0) {
        echo "   Sample: " . $matches[0]['team1'] . " vs " . $matches[0]['team2'] . " (" . $matches[0]['tournament'] . ")\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n2. Testing LolScraper...\n";
try {
    $scraper = new LolScraper();
    $matches = $scraper->scrapeMatches();
    echo "   Found: " . count($matches) . " matches\n";
    if (count($matches) > 0) {
        echo "   Sample: " . $matches[0]['team1'] . " vs " . $matches[0]['team2'] . " (" . $matches[0]['tournament'] . ")\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n3. Testing Cs2Scraper...\n";
try {
    $scraper = new Cs2Scraper();
    $matches = $scraper->scrapeMatches();
    echo "   Found: " . count($matches) . " matches\n";
    if (count($matches) > 0) {
        echo "   Sample: " . $matches[0]['team1'] . " vs " . $matches[0]['team2'] . " (" . $matches[0]['tournament'] . ")\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
