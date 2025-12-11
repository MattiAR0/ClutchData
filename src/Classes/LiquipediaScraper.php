<?php

namespace App\Classes;

use App\Interfaces\ScraperInterface;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

abstract class LiquipediaScraper implements ScraperInterface
{
    protected Client $client;
    protected string $baseUrl = 'https://liquipedia.net';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10.0,
            'headers' => [
                'User-Agent' => 'MultiGameStats-StudentProject/1.0 (contact@example.com)',
                'Accept-Encoding' => 'gzip'
            ],
            'verify' => false
        ]);
    }

    abstract public function getGameType(): string;

    /**
     * Helper to fetch HTML content.
     */
    protected function fetch(string $uri): string
    {
        try {
            $response = $this->client->request('GET', $uri);
            return (string) $response->getBody();
        } catch (\Exception $e) {
            // En un caso real, loguearíamos el error.
            return '';
        }
    }

    protected function detectRegion(string $tournamentName): string
    {
        $tournamentName = strtoupper($tournamentName);

        // Americas / NA / LATAM
        if (
            str_contains($tournamentName, 'AMERICAS') ||
            str_contains($tournamentName, 'NORTH AMERICA') ||
            str_contains($tournamentName, 'LCS') || // LoL
            str_contains($tournamentName, 'CBLOL') || // LoL Brazil
            str_contains($tournamentName, 'LLA') || // LoL LATAM
            str_contains($tournamentName, 'BRAZIL') ||
            str_contains($tournamentName, 'LATIN AMERICA') ||
            str_contains($tournamentName, 'USA')
        ) {
            return 'Americas';
        }

        // Pacific / Asia
        if (
            str_contains($tournamentName, 'PACIFIC') ||
            str_contains($tournamentName, 'LCK') || // Korea
            str_contains($tournamentName, 'KESPA') || // Korea
            str_contains($tournamentName, 'LPL') || // China
            str_contains($tournamentName, 'PCS') || // Pacific
            str_contains($tournamentName, 'VCS') || // Vietnam
            str_contains($tournamentName, 'LJL') || // Japan
            str_contains($tournamentName, 'KOREA') ||
            str_contains($tournamentName, 'JAPAN') ||
            str_contains($tournamentName, 'CHINA') ||
            str_contains($tournamentName, 'ASIA') ||
            str_contains($tournamentName, 'INDIA')
        ) {
            return 'Pacific';
        }

        // EMEA / Europe
        if (
            str_contains($tournamentName, 'EMEA') ||
            str_contains($tournamentName, 'EUROPE') ||
            str_contains($tournamentName, 'LEC') || // LoL Europe
            str_contains($tournamentName, 'TCL') || // Turkey
            str_contains($tournamentName, 'POKAL') || // DACH
            str_contains($tournamentName, 'UNITED21') || // Tier 2 EU
            str_contains($tournamentName, 'CCT') || // Tier 2 EU (often)
            str_contains($tournamentName, 'LFL') || // France
            str_contains($tournamentName, 'PRIME LEAGUE') || // DACH
            str_contains($tournamentName, 'SUPERLIGA') || // Spain
            str_contains($tournamentName, 'ULTRALIGA') || // Poland
            str_contains($tournamentName, 'CIS')
        ) {
            return 'EMEA';
        }

        // Global / International
        if (
            str_contains($tournamentName, 'CHAMPIONS') ||
            str_contains($tournamentName, 'MASTERS') ||
            str_contains($tournamentName, 'INVITATIONAL') ||
            str_contains($tournamentName, 'WORLD') ||
            str_contains($tournamentName, 'MSI') ||
            str_contains($tournamentName, 'MAJOR')
        ) {
            return 'International';
        }

        return 'Other';
    }
}
