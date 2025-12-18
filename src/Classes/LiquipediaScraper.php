<?php

declare(strict_types=1);

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
    abstract public function scrapeMatchDetails(string $url): array;

    protected function fetch(string $uri): string
    {
        $maxRetries = 3;
        $retryDelay = 5; // Start with 5 seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Add a courtesy delay between requests (Liquipedia requires this)
                if ($attempt > 1) {
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                }

                $response = $this->client->request('GET', $uri);

                // Success - add small delay before next potential request
                usleep(2000000); // 2 seconds courtesy delay

                return (string) $response->getBody();
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                if ($statusCode === 429 && $attempt < $maxRetries) {
                    // Rate limited - wait and retry
                    error_log("Liquipedia rate limited (429), waiting {$retryDelay}s before retry $attempt/$maxRetries");
                    sleep($retryDelay);
                    $retryDelay *= 2;
                    continue;
                }

                error_log("Liquipedia fetch error for $uri: " . $e->getMessage());
                return '';
            } catch (\Exception $e) {
                error_log("Liquipedia fetch error for $uri: " . $e->getMessage());
                return '';
            }
        }

        return '';
    }

    protected function detectRegion(string $tournamentName): string
    {
        $tournamentName = strtoupper($tournamentName);

        // Pacific / Asia - CHECK FIRST (many tournaments have Asian references)
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
            str_contains($tournamentName, 'INDIA') ||
            // New patterns for Asian tournaments
            str_contains($tournamentName, 'KR&JP') ||
            str_contains($tournamentName, 'KR ') ||
            str_contains($tournamentName, ' KR') ||
            str_contains($tournamentName, 'JP ') ||
            str_contains($tournamentName, ' JP') ||
            str_contains($tournamentName, 'KOREAN') ||
            str_contains($tournamentName, 'JAPANESE') ||
            str_contains($tournamentName, 'CHINESE') ||
            str_contains($tournamentName, 'DEMACIA') || // LoL China event
            str_contains($tournamentName, 'IONIA') || // LoL China event
            str_contains($tournamentName, 'SEA ') || // Southeast Asia
            str_contains($tournamentName, ' SEA') ||
            str_contains($tournamentName, 'SOUTHEAST') ||
            str_contains($tournamentName, 'INDONESIA') ||
            str_contains($tournamentName, ' ID ') || // Indonesia abbrev
            str_contains($tournamentName, 'VIETNAM') ||
            str_contains($tournamentName, 'THAILAND') ||
            str_contains($tournamentName, 'PHILIPPINES') ||
            str_contains($tournamentName, 'TAIWAN') ||
            str_contains($tournamentName, 'HONG KONG') ||
            str_contains($tournamentName, 'SINGAPORE') ||
            str_contains($tournamentName, 'MALAYSIA') ||
            str_contains($tournamentName, 'OCEANIA') ||
            str_contains($tournamentName, 'OCE ') ||
            str_contains($tournamentName, 'EXTREMESLAND') || // Asian CS2 event
            str_contains($tournamentName, 'GALAXY BATTLE') // Asian event
        ) {
            return 'Pacific';
        }

        // EMEA / Europe - CHECK SECOND
        if (
            str_contains($tournamentName, 'EMEA') ||
            str_contains($tournamentName, 'EUROPE') ||
            str_contains($tournamentName, 'EUROPEAN') ||
            str_contains($tournamentName, 'LEC') || // LoL Europe
            str_contains($tournamentName, 'TCL') || // Turkey
            str_contains($tournamentName, 'POKAL') || // DACH
            str_contains($tournamentName, 'UNITED21') || // Tier 2 EU
            str_contains($tournamentName, 'CCT') || // Tier 2 EU (often)
            str_contains($tournamentName, 'LFL') || // France
            str_contains($tournamentName, 'PRIME LEAGUE') || // DACH
            str_contains($tournamentName, 'SUPERLIGA') || // Spain
            str_contains($tournamentName, 'ULTRALIGA') || // Poland
            str_contains($tournamentName, 'CIS') ||
            // New patterns for European tournaments
            str_contains($tournamentName, 'EU ') ||
            str_contains($tournamentName, ' EU') ||
            str_contains($tournamentName, 'ESEA') ||
            str_contains($tournamentName, 'FACEIT') ||
            str_contains($tournamentName, 'TURKEY') ||
            str_contains($tournamentName, 'TURKISH') ||
            str_contains($tournamentName, 'RUSSIA') ||
            str_contains($tournamentName, 'RUSSIAN') ||
            str_contains($tournamentName, ' RU ') ||
            str_contains($tournamentName, 'SPAIN') ||
            str_contains($tournamentName, 'SPANISH') ||
            str_contains($tournamentName, 'FRANCE') ||
            str_contains($tournamentName, 'FRENCH') ||
            str_contains($tournamentName, 'GERMANY') ||
            str_contains($tournamentName, 'GERMAN') ||
            str_contains($tournamentName, 'POLAND') ||
            str_contains($tournamentName, 'POLISH') ||
            str_contains($tournamentName, 'NORDIC') ||
            str_contains($tournamentName, 'BENELUX') ||
            str_contains($tournamentName, 'BALKAN') ||
            str_contains($tournamentName, 'ARABIAN') ||
            str_contains($tournamentName, 'MENA') ||
            str_contains($tournamentName, 'MIDDLE EAST') ||
            str_contains($tournamentName, 'DRACULAN') // EU event
        ) {
            return 'EMEA';
        }

        // Americas / NA / LATAM
        if (
            str_contains($tournamentName, 'AMERICAS') ||
            str_contains($tournamentName, 'NORTH AMERICA') ||
            str_contains($tournamentName, 'LCS') || // LoL
            str_contains($tournamentName, 'CBLOL') || // LoL Brazil
            str_contains($tournamentName, 'LLA') || // LoL LATAM
            str_contains($tournamentName, 'BRAZIL') ||
            str_contains($tournamentName, 'BRAZILIAN') ||
            str_contains($tournamentName, 'LATIN AMERICA') ||
            str_contains($tournamentName, 'USA') ||
            // New patterns
            str_contains($tournamentName, ' NA ') ||
            str_contains($tournamentName, 'NA ') ||
            str_contains($tournamentName, ' NA') ||
            str_contains($tournamentName, 'LATAM') ||
            str_contains($tournamentName, 'MEXICO') ||
            str_contains($tournamentName, 'MEXICAN') ||
            str_contains($tournamentName, 'ARGENTINA') ||
            str_contains($tournamentName, 'CHILE') ||
            str_contains($tournamentName, 'PERU') ||
            str_contains($tournamentName, 'COLOMBIA') ||
            str_contains($tournamentName, 'CANADA') ||
            str_contains($tournamentName, 'CANADIAN')
        ) {
            return 'Americas';
        }

        // Global / International
        if (
            str_contains($tournamentName, 'CHAMPIONS') ||
            str_contains($tournamentName, 'MASTERS') ||
            str_contains($tournamentName, 'INVITATIONAL') ||
            str_contains($tournamentName, 'WORLD') ||
            str_contains($tournamentName, 'MSI') ||
            str_contains($tournamentName, 'MAJOR') ||
            str_contains($tournamentName, 'INTERNATIONAL') ||
            str_contains($tournamentName, 'GLOBAL')
        ) {
            return 'International';
        }

        return 'Other';
    }

    protected function extractScore(Crawler $node, bool $isLeft): ?int
    {
        // Try the opponent-specific selector first
        $selector = $isLeft
            ? '.match-info-header-opponent-left .match-info-header-opponent-score'
            : '.match-info-header-opponent:not(.match-info-header-opponent-left) .match-info-header-opponent-score';

        $scoreNode = $node->filter($selector);
        if ($scoreNode->count()) {
            $text = trim($scoreNode->text());
            return is_numeric($text) ? (int) $text : null;
        }

        // Try the central scoreholder selector
        $centralSelector = '.match-info-header-scoreholder-score';
        $centralNodes = $node->filter($centralSelector);

        if ($centralNodes->count() >= 2) {
            // First is left, second is right
            $scoreNode = $isLeft ? $centralNodes->eq(0) : $centralNodes->eq(1);
            $text = trim($scoreNode->text());
            return is_numeric($text) ? (int) $text : null;
        }

        return null;
    }

    protected function detectStatus(Crawler $node): string
    {
        if ($node->filter('.timer-object-live')->count() > 0) {
            return 'live';
        }

        $s1 = $this->extractScore($node, true);
        $s2 = $this->extractScore($node, false);

        if ($s1 !== null && $s2 !== null) {
            // If scores are 0-0, it's likely upcoming despite the potential past date
            // unless we have specific logic to say otherwise. For now, 0-0 is upcoming.
            if ($s1 === 0 && $s2 === 0) {
                return 'upcoming';
            }
            return 'completed';
        }

        return 'upcoming';
    }

    protected function extractMatchUrl(Crawler $node, string $pathPrefix): ?string
    {
        $urlNode = $node->filter('a[title="View match details"]');
        if ($urlNode->count()) {
            $href = $urlNode->attr('href');
            return str_contains($href, 'https') ? $href : 'https://liquipedia.net' . $href;
        }

        $links = $node->filter('a');
        foreach ($links as $link) {
            if ($link instanceof \DOMElement) {
                $href = $link->getAttribute('href');
                if (str_contains($href, $pathPrefix)) {
                    return str_contains($href, 'https') ? $href : 'https://liquipedia.net' . $href;
                }
            }
        }

        return null;
    }

    protected function calculateImportance(string $tournament, string $region): int
    {
        $tournament = strtoupper($tournament);
        $score = 0;

        // Base Region Scores
        if ($region === 'International')
            $score = 90;
        elseif ($region === 'Americas' || $region === 'EMEA' || $region === 'Pacific')
            $score = 50;
        else
            $score = 20;

        // Tier 1 Keywords - Massive Boost
        if (
            str_contains($tournament, 'WORLD') ||
            str_contains($tournament, 'CHAMPIONS') ||
            str_contains($tournament, 'MAJOR') ||
            str_contains($tournament, 'MSI') ||
            str_contains($tournament, 'INVITATIONAL')
        ) {
            $score += 50;
        }

        // Playoff Stages - Boost
        if (str_contains($tournament, 'FINAL') || str_contains($tournament, 'PLAYOFF')) {
            $score += 20;
        }

        // Specific Leagues (LoL/Valo)
        if (
            str_contains($tournament, 'LEC') ||
            str_contains($tournament, 'LCS') ||
            str_contains($tournament, 'LCK') ||
            str_contains($tournament, 'LPL') ||
            str_contains($tournament, 'VCT')
        ) {
            $score += 30;
        }

        return min($score, 200); // Cap at 200
    }
}
