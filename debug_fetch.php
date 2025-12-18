<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = new Client([
    'base_uri' => 'https://liquipedia.net',
    'timeout' => 15.0,
    'headers' => [
        'User-Agent' => 'MultiGameStats-StudentProject/1.0 (contact@example.com)',
        'Accept-Encoding' => 'gzip'
    ],
    'verify' => false
]);

echo "=== Fetching Valorant Matches Page ===\n\n";

try {
    $response = $client->request('GET', '/valorant/Liquipedia:Matches');
    $html = (string) $response->getBody();

    echo "Response length: " . strlen($html) . " bytes\n\n";

    // Save to file for inspection
    file_put_contents('debug_valorant_html.html', $html);
    echo "HTML saved to debug_valorant_html.html\n\n";

    // Check for match-related selectors
    $selectors = [
        'div.match-info' => substr_count($html, 'match-info'),
        'match-info-header' => substr_count($html, 'match-info-header'),
        'match-row' => substr_count($html, 'match-row'),
        'infobox_matches' => substr_count($html, 'infobox_matches'),
        'matches-list' => substr_count($html, 'matches-list'),
        'upcoming-matches' => substr_count($html, 'upcoming-matches'),
        'wikitable' => substr_count($html, 'wikitable'),
    ];

    echo "Selector occurrences in HTML:\n";
    foreach ($selectors as $name => $count) {
        echo "  $name: $count\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
