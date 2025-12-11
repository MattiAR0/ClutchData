<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;

// Helper to print results
function printParams($game, $matches)
{
    echo "[$game] Found " . count($matches) . " matches.\n";
    if (count($matches) > 0) {
        echo "Example match:\n";
        print_r($matches[0]);
    }
    echo "---------------------------------------------------\n";
}

echo "Starting Scraper Debug...\n";

// Test Valorant
try {
    echo "Scraping Valorant...\n";
    $vScraper = new ValorantScraper();
    $vMatches = $vScraper->scrapeMatches();
    printParams('Valorant', $vMatches);
} catch (Exception $e) {
    echo "Valorant Error: " . $e->getMessage() . "\n";
}

// Test LoL
try {
    echo "Scraping LoL...\n";
    $lScraper = new LolScraper();
    $lMatches = $lScraper->scrapeMatches();
    printParams('LoL', $lMatches);
} catch (Exception $e) {
    echo "LoL Error: " . $e->getMessage() . "\n";
}

// Test CS2
try {
    echo "Scraping CS2...\n";
    $cScraper = new Cs2Scraper();
    $cMatches = $cScraper->scrapeMatches();
    printParams('CS2', $cMatches);
} catch (Exception $e) {
    echo "CS2 Error: " . $e->getMessage() . "\n";
}

echo "Debug Completed.\n";
