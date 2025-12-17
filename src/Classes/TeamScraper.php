<?php

namespace App\Classes;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

/**
 * Scraper for team information from Liquipedia
 * Uses on-demand scraping when a team page is requested
 */
class TeamScraper
{
    protected Client $client;
    protected string $baseUrl = 'https://liquipedia.net';

    // Game-specific URL prefixes
    protected array $gamePaths = [
        'valorant' => '/valorant/',
        'lol' => '/leagueoflegends/',
        'cs2' => '/counterstrike/'
    ];

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 15.0,
            'headers' => [
                'User-Agent' => 'MultiGameStats-StudentProject/1.0 (contact@example.com)',
                'Accept-Encoding' => 'gzip'
            ],
            'verify' => false
        ]);
    }

    /**
     * Fetch HTML content from Liquipedia
     */
    protected function fetch(string $uri): string
    {
        try {
            $response = $this->client->request('GET', $uri);
            return (string) $response->getBody();
        } catch (Exception $e) {
            error_log("TeamScraper fetch error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Build Liquipedia URL for a team
     */
    public function buildTeamUrl(string $teamName, string $gameType): string
    {
        $path = $this->gamePaths[$gameType] ?? '/valorant/';
        // URL encode team name, replacing spaces with underscores
        $encodedName = str_replace(' ', '_', $teamName);
        return $path . urlencode($encodedName);
    }

    /**
     * Scrape team information from Liquipedia
     */
    public function scrapeTeam(string $teamName, string $gameType): ?array
    {
        $url = $this->buildTeamUrl($teamName, $gameType);
        $html = $this->fetch($url);

        if (empty($html)) {
            return null;
        }

        $crawler = new Crawler($html);

        return [
            'name' => $teamName,
            'game_type' => $gameType,
            'region' => $this->extractRegion($crawler),
            'country' => $this->extractCountry($crawler),
            'logo_url' => $this->extractLogo($crawler),
            'description' => $this->extractDescription($crawler),
            'liquipedia_url' => $this->baseUrl . $url,
            'roster' => $this->extractRoster($crawler, $gameType),
            'results' => $this->extractResults($crawler)
        ];
    }

    /**
     * Extract team region from infobox
     */
    protected function extractRegion(Crawler $crawler): string
    {
        try {
            // Try to find region in infobox
            $infobox = $crawler->filter('.infobox-cell-2');
            foreach ($infobox as $cell) {
                $text = trim($cell->textContent);
                if (in_array($text, ['Americas', 'EMEA', 'Pacific', 'Europe', 'North America', 'Asia'])) {
                    return $text;
                }
            }

            // Fallback: check category links
            $categories = $crawler->filter('a[href*="Category:"]');
            foreach ($categories as $cat) {
                $href = $cat->getAttribute('href');
                if (str_contains($href, 'North_America'))
                    return 'Americas';
                if (str_contains($href, 'South_America'))
                    return 'Americas';
                if (str_contains($href, 'Europe'))
                    return 'EMEA';
                if (str_contains($href, 'Asia'))
                    return 'Pacific';
                if (str_contains($href, 'Korea'))
                    return 'Pacific';
                if (str_contains($href, 'China'))
                    return 'Pacific';
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return 'Other';
    }

    /**
     * Extract team country from infobox
     */
    protected function extractCountry(Crawler $crawler): ?string
    {
        try {
            // Look for country flag/link in infobox
            $flagNode = $crawler->filter('.infobox-image a[href*="Category:"]');
            if ($flagNode->count() > 0) {
                $href = $flagNode->first()->attr('href');
                if (preg_match('/Category:([A-Za-z_]+)$/', $href, $matches)) {
                    return str_replace('_', ' ', $matches[1]);
                }
            }

            // Alternative: look for location row
            $locationCell = $crawler->filter('.infobox-cell-2 a[title]');
            foreach ($locationCell as $cell) {
                if ($cell instanceof \DOMElement) {
                    $title = $cell->getAttribute('title');
                    // Common countries
                    if (in_array($title, ['United States', 'Korea', 'China', 'Brazil', 'Germany', 'France', 'Spain', 'United Kingdom'])) {
                        return $title;
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract team logo URL
     */
    protected function extractLogo(Crawler $crawler): ?string
    {
        try {
            $logoNode = $crawler->filter('.infobox-image img');
            if ($logoNode->count() > 0) {
                $src = $logoNode->first()->attr('src');
                // Handle relative URLs
                if ($src && !str_starts_with($src, 'http')) {
                    return 'https://liquipedia.net' . $src;
                }
                return $src;
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract team description (first paragraph)
     */
    protected function extractDescription(Crawler $crawler): ?string
    {
        try {
            // Get first paragraph after infobox
            $paragraphs = $crawler->filter('.mw-parser-output > p');
            foreach ($paragraphs as $p) {
                $text = trim($p->textContent);
                if (strlen($text) > 50) {
                    // Clean up reference markers
                    $text = preg_replace('/\[\d+\]/', '', $text);
                    return $text;
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract active roster from team page
     */
    protected function extractRoster(Crawler $crawler, string $gameType): array
    {
        $players = [];

        try {
            // Look for roster table - different games may have different structures
            $rosterSection = $crawler->filter('.roster-card, .teamcard, table.wikitable');

            if ($rosterSection->count() > 0) {
                // Try roster-card format (common for Valorant)
                $rosterCards = $crawler->filter('.roster-card .player-row, .roster-card tr');

                if ($rosterCards->count() > 0) {
                    $rosterCards->each(function (Crawler $row) use (&$players, $gameType) {
                        $this->extractPlayerFromRow($row, $players, $gameType);
                    });
                }

                // If no players found, try wikitable format
                if (empty($players)) {
                    $tables = $crawler->filter('table.wikitable');
                    $tables->each(function (Crawler $table) use (&$players, $gameType) {
                        // Check if this is a roster table by looking at headers
                        $headers = $table->filter('th');
                        $isRoster = false;
                        $headers->each(function (Crawler $th) use (&$isRoster) {
                            $text = strtolower(trim($th->text()));
                            if (in_array($text, ['player', 'id', 'name', 'role'])) {
                                $isRoster = true;
                            }
                        });

                        if ($isRoster) {
                            $table->filter('tr')->each(function (Crawler $row) use (&$players, $gameType) {
                                $this->extractPlayerFromRow($row, $players, $gameType);
                            });
                        }
                    });
                }
            }

            // Alternative: look for player links in Active section
            if (empty($players)) {
                $activeSection = $crawler->filter('#Active, .mw-headline:contains("Active")');
                if ($activeSection->count() > 0) {
                    // Get the parent section and find player links
                    $crawler->filter('a[href*="/valorant/"], a[href*="/leagueoflegends/"], a[href*="/counterstrike/"]')
                        ->each(function (Crawler $link) use (&$players, $gameType) {
                            $href = $link->attr('href');
                            $name = trim($link->text());

                            // Filter out non-player links
                            if (
                                strlen($name) > 1 && strlen($name) < 30 &&
                                !str_contains($href, 'Tournament') &&
                                !str_contains($href, 'Match:') &&
                                !str_contains($name, ':')
                            ) {
                                // Avoid duplicates
                                $exists = array_filter($players, fn($p) => $p['nickname'] === $name);
                                if (empty($exists) && count($players) < 10) {
                                    $players[] = [
                                        'nickname' => $name,
                                        'role' => null,
                                        'country' => null,
                                        'liquipedia_url' => $this->baseUrl . $href
                                    ];
                                }
                            }
                        });
                }
            }
        } catch (Exception $e) {
            error_log("Error extracting roster: " . $e->getMessage());
        }

        return $players;
    }

    /**
     * Helper to extract player info from a table row
     */
    protected function extractPlayerFromRow(Crawler $row, array &$players, string $gameType): void
    {
        try {
            $cells = $row->filter('td');
            if ($cells->count() < 1)
                return;

            $playerLink = $row->filter('a');
            if ($playerLink->count() > 0) {
                $name = trim($playerLink->first()->text());
                $href = $playerLink->first()->attr('href');

                if (strlen($name) > 1 && strlen($name) < 30) {
                    // Try to get role
                    $role = null;
                    $cells->each(function (Crawler $cell) use (&$role) {
                        $text = strtolower(trim($cell->text()));
                        if (in_array($text, ['igl', 'captain', 'duelist', 'controller', 'sentinel', 'initiator', 'support', 'mid', 'top', 'jungle', 'adc', 'bot', 'awper', 'rifler', 'entry'])) {
                            $role = ucfirst($text);
                        }
                    });

                    // Avoid duplicates
                    $exists = array_filter($players, fn($p) => $p['nickname'] === $name);
                    if (empty($exists)) {
                        $players[] = [
                            'nickname' => $name,
                            'role' => $role,
                            'country' => null,
                            'liquipedia_url' => $href ? $this->baseUrl . $href : null
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Skip problematic rows
        }
    }

    /**
     * Extract recent results/tournament placements
     */
    protected function extractResults(Crawler $crawler): array
    {
        $results = [];

        try {
            // Look for results table
            $resultsTable = $crawler->filter('.infobox-center table.wikitable, #Results + table.wikitable');

            if ($resultsTable->count() > 0) {
                $resultsTable->first()->filter('tr')->each(function (Crawler $row, int $i) use (&$results) {
                    if ($i === 0)
                        return; // Skip header

                    $cells = $row->filter('td');
                    if ($cells->count() >= 3) {
                        $results[] = [
                            'date' => trim($cells->eq(0)->text()),
                            'placement' => trim($cells->eq(1)->text()),
                            'tournament' => trim($cells->eq(2)->text()),
                        ];
                    }
                });
            }

            // Limit to recent results
            $results = array_slice($results, 0, 10);
        } catch (Exception $e) {
            // Ignore errors
        }

        return $results;
    }
}
