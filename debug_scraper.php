<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\ValorantScraper;
use App\Classes\Cs2Scraper;

ob_start();

// Helper to print specific details
function testScrape($scraper, $url)
{
    echo "Testing using " . get_class($scraper) . "\n";
    echo "URL: $url\n";
    try {
        $details = $scraper->scrapeMatchDetails($url);
        echo "Maps Found: " . count($details['maps']) . "\n";
        print_r($details['maps']);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "---------------------------------------------------\n";
}

$valScraper = new ValorantScraper();
$cs2Scraper = new Cs2Scraper();

// Hardcoded config from .env
$host = 'localhost';
$dbname = 'clutchdata_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Querying for Valorant match...\n";
    // Get a recent completed Valorant match with URL
    $stmt = $pdo->query("SELECT match_url, game_type FROM matches WHERE game_type = 'valorant' AND match_url IS NOT NULL AND match_url != '' AND match_status = 'completed' ORDER BY match_time DESC LIMIT 1");
    $valMatch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($valMatch) {
        testScrape($valScraper, $valMatch['match_url']);
    } else {
        echo "No completed Valorant match found. Trying ANY match.\n";
        $stmt = $pdo->query("SELECT match_url, game_type FROM matches WHERE game_type = 'valorant' AND match_url IS NOT NULL AND match_url != '' LIMIT 1");
        $valMatch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($valMatch) {
            testScrape($valScraper, $valMatch['match_url']);
        }
    }

    echo "Querying for CS2 match...\n";
    // Get a recent completed CS2 match with URL
    $stmt = $pdo->query("SELECT match_url, game_type FROM matches WHERE game_type = 'cs2' AND match_url IS NOT NULL AND match_url != '' AND match_status = 'completed' ORDER BY match_time DESC LIMIT 1");
    $cs2Match = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cs2Match) {
        testScrape($cs2Scraper, $cs2Match['match_url']);
    } else {
        echo "No completed CS2 match found.\n";
    }

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}

$output = ob_get_clean();
file_put_contents(__DIR__ . '/debug_output.txt', $output);
echo "Done. Output written to debug_output.txt\n";
