<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$client = new Client([
    'base_uri' => 'https://liquipedia.net',
    'timeout' => 10.0,
    'headers' => [
        'User-Agent' => 'MultiGameStats-Debug/1.0',
        'Accept-Encoding' => 'gzip'
    ],
    'verify' => false
]);

echo "Fetching raw HTML for detailed inspection...\n";
$response = $client->request('GET', '/counterstrike/Liquipedia:Matches');
$html = (string) $response->getBody();

$crawler = new Crawler($html);
$node = $crawler->filter('div.match-info')->first();

if ($node->count()) {
    echo "First match HTML:\n";
    echo $node->html();
} else {
    echo "No match-info div found.\n";
}
