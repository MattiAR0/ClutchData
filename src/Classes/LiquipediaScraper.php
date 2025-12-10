<?php

namespace App\Classes;

use App\Interfaces\ScraperInterface;
use GuzzleHttp\Client;

abstract class LiquipediaScraper implements ScraperInterface
{
    protected Client $client;
    protected string $baseUrl = 'https://liquipedia.net';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 10.0,
            'headers'  => [
                'User-Agent' => 'MultiGameStats-StudentProject/1.0 (contact@example.com)',
                'Accept-Encoding' => 'gzip' // Importante para Liquipedia
            ]
        ]);
    }

    abstract public function getGameType(): string;
}
