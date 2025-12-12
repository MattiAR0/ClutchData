<?php

require 'vendor/autoload.php';

use App\Classes\Cs2Scraper;

$scraper = new Cs2Scraper();
echo "Fetching matches from Liquipedia...\n";
$matches = $scraper->scrapeMatches();

echo "Found " . count($matches) . " matches.\n";

$now = new DateTime();
$misclassified = 0;

foreach ($matches as $match) {
    if ($match['match_status'] === 'upcoming') {
        $matchTime = new DateTime($match['time']);
        if ($matchTime < $now) {
            $misclassified++;
            echo "POSSIBLE MISCLASSIFIED (Past Upcoming): " . $match['team1'] . " vs " . $match['team2'] . " @ " . $match['time'] . "\n";
            // Check if scores are null
            if ($match['team1_score'] === null && $match['team2_score'] === null) {
                echo "  -> Scores are NULL. Match likely completed but score selector failed.\n";
            }
        }
    } else if ($match['match_status'] === 'completed') {
        echo "COMPLETED MATCH FOUND: " . $match['team1'] . " vs " . $match['team2'] . " Score: " . $match['team1_score'] . "-" . $match['team2_score'] . "\n";
    }
}

if ($misclassified > 0) {
    echo "\nFound $misclassified matches in the past marked as upcoming.\n";
} else {
    echo "\nNo past matches marked as upcoming found. Maybe they are not being scraped at all?\n";
}
