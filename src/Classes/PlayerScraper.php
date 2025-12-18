<?php

declare(strict_types=1);

namespace App\Classes;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

/**
 * Scraper for player information from Liquipedia
 * Uses on-demand scraping when a player page is requested
 */
class PlayerScraper
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
            error_log("PlayerScraper fetch error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Build Liquipedia URL for a player
     */
    public function buildPlayerUrl(string $playerName, string $gameType): string
    {
        $path = $this->gamePaths[$gameType] ?? '/valorant/';
        // URL encode player name, replacing spaces with underscores
        $encodedName = str_replace(' ', '_', $playerName);
        return $path . urlencode($encodedName);
    }

    /**
     * Scrape player information from Liquipedia
     */
    public function scrapePlayer(string $playerName, string $gameType): ?array
    {
        $url = $this->buildPlayerUrl($playerName, $gameType);
        $html = $this->fetch($url);

        if (empty($html)) {
            return null;
        }

        $crawler = new Crawler($html);

        return [
            'nickname' => $playerName,
            'game_type' => $gameType,
            'real_name' => $this->extractRealName($crawler),
            'country' => $this->extractCountry($crawler),
            'role' => $this->extractRole($crawler, $gameType),
            'photo_url' => $this->extractPhoto($crawler),
            'birthdate' => $this->extractBirthdate($crawler),
            'description' => $this->extractDescription($crawler),
            'current_team' => $this->extractCurrentTeam($crawler),
            'liquipedia_url' => $this->baseUrl . $url,
            'achievements' => $this->extractAchievements($crawler),
            'team_history' => $this->extractTeamHistory($crawler)
        ];
    }

    /**
     * Extract player's real name from infobox
     */
    protected function extractRealName(Crawler $crawler): ?string
    {
        try {
            // Look for name in infobox header
            $nameNode = $crawler->filter('.infobox-header');
            if ($nameNode->count() > 0) {
                $fullName = trim($nameNode->first()->text());
                // Remove nickname in quotes if present
                if (preg_match('/"[^"]+"/', $fullName)) {
                    $fullName = preg_replace('/"[^"]+"/', '', $fullName);
                    return trim($fullName);
                }
            }

            // Try birth name row
            $rows = $crawler->filter('.infobox-cell-2');
            foreach ($rows as $row) {
                $prevSibling = $row->previousSibling;
                if ($prevSibling && str_contains(strtolower($prevSibling->textContent), 'name')) {
                    return trim($row->textContent);
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract player's country from infobox
     */
    protected function extractCountry(Crawler $crawler): ?string
    {
        try {
            // Look for country flag/link in infobox
            $flagNode = $crawler->filter('.infobox a[href*="Category:"]');
            foreach ($flagNode as $node) {
                if ($node instanceof \DOMElement) {
                    $href = $node->getAttribute('href');
                    // Check if it's a country category
                    if (preg_match('/Category:([A-Za-z_]+)$/', $href, $matches)) {
                        $country = str_replace('_', ' ', $matches[1]);
                        // Validate it's a country (basic check)
                        $countries = [
                            'United States',
                            'Canada',
                            'Korea',
                            'China',
                            'Brazil',
                            'Germany',
                            'France',
                            'Spain',
                            'United Kingdom',
                            'Japan',
                            'Russia',
                            'Denmark',
                            'Sweden',
                            'Norway',
                            'Finland',
                            'Poland',
                            'Turkey',
                            'Ukraine',
                            'Argentina',
                            'Chile',
                            'Mexico',
                            'Portugal',
                            'Italy',
                            'Netherlands'
                        ];
                        if (in_array($country, $countries)) {
                            return $country;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract player's role
     */
    protected function extractRole(Crawler $crawler, string $gameType): ?string
    {
        try {
            // Look for role in infobox
            $infoboxCells = $crawler->filter('.infobox-cell-2');
            foreach ($infoboxCells as $cell) {
                $text = strtolower(trim($cell->textContent));

                // Game-specific roles
                if ($gameType === 'valorant') {
                    $roles = ['duelist', 'controller', 'sentinel', 'initiator', 'igl'];
                } elseif ($gameType === 'lol') {
                    $roles = ['top', 'jungle', 'mid', 'adc', 'support', 'bot'];
                } else { // cs2
                    $roles = ['awper', 'rifler', 'entry', 'igl', 'support', 'lurker'];
                }

                foreach ($roles as $role) {
                    if (str_contains($text, $role)) {
                        return ucfirst($role);
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract player photo URL
     */
    protected function extractPhoto(Crawler $crawler): ?string
    {
        try {
            $photoNode = $crawler->filter('.infobox-image img');
            if ($photoNode->count() > 0) {
                $src = $photoNode->first()->attr('src');
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
     * Extract player's birthdate
     */
    protected function extractBirthdate(Crawler $crawler): ?string
    {
        try {
            // Look for birthdate in infobox
            $dateNode = $crawler->filter('.infobox-cell-2');
            foreach ($dateNode as $cell) {
                $text = trim($cell->textContent);
                // Look for date patterns like "May 5, 2001" or "2001-05-05"
                if (preg_match('/\b(\d{4}[-\/]\d{2}[-\/]\d{2})\b/', $text, $matches)) {
                    return $matches[1];
                }
                if (preg_match('/\b([A-Z][a-z]+\s+\d{1,2},?\s+\d{4})\b/', $text, $matches)) {
                    $date = strtotime($matches[1]);
                    if ($date) {
                        return date('Y-m-d', $date);
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract player description (bio paragraph)
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
     * Extract current team name
     */
    protected function extractCurrentTeam(Crawler $crawler): ?string
    {
        try {
            // Look for current team in infobox
            $teamNode = $crawler->filter('.infobox a[href*="/valorant/"], .infobox a[href*="/leagueoflegends/"], .infobox a[href*="/counterstrike/"]');
            foreach ($teamNode as $node) {
                if ($node instanceof \DOMElement) {
                    $text = trim($node->textContent);
                    $href = $node->getAttribute('href');
                    // Exclude player links (typically short names in quotes)
                    if (strlen($text) > 1 && !str_contains($href, 'Match:') && !str_contains($href, 'Tournament')) {
                        return $text;
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return null;
    }

    /**
     * Extract notable achievements
     */
    protected function extractAchievements(Crawler $crawler): array
    {
        $achievements = [];

        try {
            // Look for achievements section
            $achievementRows = $crawler->filter('#Achievements + table tr, #Awards + table tr');

            if ($achievementRows->count() > 0) {
                $achievementRows->each(function (Crawler $row, int $i) use (&$achievements) {
                    if ($i === 0)
                        return; // Skip header

                    $cells = $row->filter('td');
                    if ($cells->count() >= 2) {
                        $achievements[] = [
                            'year' => trim($cells->eq(0)->text()),
                            'achievement' => trim($cells->eq(1)->text())
                        ];
                    }
                });
            }

            // Limit achievements
            $achievements = array_slice($achievements, 0, 5);
        } catch (Exception $e) {
            // Ignore errors
        }

        return $achievements;
    }

    /**
     * Extract team history
     */
    protected function extractTeamHistory(Crawler $crawler): array
    {
        $history = [];

        try {
            // Look for team history table or timeline
            $historyNodes = $crawler->filter('.infobox a[href*="/valorant/"], .infobox a[href*="/leagueoflegends/"], .infobox a[href*="/counterstrike/"]');

            foreach ($historyNodes as $node) {
                if ($node instanceof \DOMElement) {
                    $text = trim($node->textContent);
                    $href = $node->getAttribute('href');

                    // Only include team pages
                    if (
                        strlen($text) > 1 && !str_contains($href, 'Match:') &&
                        !str_contains($href, 'Tournament') && !str_contains($href, 'Category:')
                    ) {
                        // Avoid duplicates
                        if (!in_array($text, array_column($history, 'team'))) {
                            $history[] = ['team' => $text];
                        }
                    }
                }
            }

            // Limit history
            $history = array_slice($history, 0, 10);
        } catch (Exception $e) {
            // Ignore errors
        }

        return $history;
    }
}
