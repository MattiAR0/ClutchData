<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client([
    'timeout' => 10.0,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
    ],
    'verify' => false
]);

function testUrl($client, $url, $name)
{
    echo "Testing $name ($url)...\n";
    try {
        $response = $client->request('GET', $url);
        echo "Status: " . $response->getStatusCode() . "\n";
        echo "Size: " . strlen($response->getBody()) . " bytes\n";
        echo "Preview: " . substr(strip_tags($response->getBody()), 0, 100) . "\n\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

testUrl($client, 'https://www.hltv.org/matches', 'HLTV');
testUrl($client, 'https://www.vlr.gg/matches', 'vlr.gg');
