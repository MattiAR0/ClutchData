<?php

declare(strict_types=1);

namespace App\Classes;

use App\Traits\AntiBlockingTrait;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

/**
 * Scraper for team information from Liquipedia
 * Uses on-demand scraping when a team page is requested
 */
class TeamScraper
{
    use AntiBlockingTrait;

    protected Client $client;
    protected string $baseUrl = 'https://liquipedia.net';

    // Game-specific URL prefixes
    protected array $gamePaths = [
        'valorant' => '/valorant/',
        'lol' => '/leagueoflegends/',
        'cs2' => '/counterstrike/'
    ];

    // Role mappings per game
    protected array $rolesByGame = [
        'valorant' => ['duelist', 'controller', 'sentinel', 'initiator', 'igl', 'flex'],
        'lol' => ['top', 'jungle', 'mid', 'adc', 'support', 'bot'],
        'cs2' => ['awper', 'rifler', 'entry', 'igl', 'support', 'lurker']
    ];

    public function __construct()
    {
        // Configuración para Liquipedia teams
        $this->baseDelayMs = 1500;
        $this->jitterFactor = 0.25;
        $this->maxRetries = 3;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 15.0,
            'headers' => [
                'User-Agent' => 'MultiGameStats-StudentProject/1.0 (contact@example.com)',
                'Accept-Encoding' => 'gzip'
            ],
            'verify' => false,
            'allow_redirects' => true
        ]);
    }

    /**
     * Fetch HTML content from Liquipedia with anti-blocking
     */
    protected function fetch(string $uri): string
    {
        $this->applySmartRateLimit();

        try {
            $headers = $this->getRandomHeaders();
            $headers['User-Agent'] = 'MultiGameStats-StudentProject/1.0 (contact@example.com)';

            $response = $this->client->request('GET', $uri, [
                'headers' => $headers
            ]);

            $this->registerSuccess();
            return (string) $response->getBody();
        } catch (Exception $e) {
            $this->registerFailure();
            error_log("TeamScraper fetch error for $uri: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Normalize team name for URL (handle case sensitivity)
     */
    protected function normalizeTeamName(string $teamName): string
    {
        // Common team name corrections
        $corrections = [
            'FNATIC' => 'Fnatic',
            'fnatic' => 'Fnatic',
            'NAVI' => 'Natus_Vincere',
            'NaVi' => 'Natus_Vincere',
            'Na\'Vi' => 'Natus_Vincere',
            'G2' => 'G2_Esports',
            'C9' => 'Cloud9',
            'TL' => 'Team_Liquid',
            'TSM' => 'TSM',
            'LOUD' => 'LOUD',
            'DRX' => 'DRX',
            'T1' => 'T1',
            'GEN' => 'Gen.G',
            'GEN.G' => 'Gen.G',
            'GENG' => 'Gen.G',
        ];

        // Check for known corrections first
        if (isset($corrections[strtoupper($teamName)])) {
            return $corrections[strtoupper($teamName)];
        }
        if (isset($corrections[$teamName])) {
            return $corrections[$teamName];
        }

        return $teamName;
    }

    /**
     * Build Liquipedia URL for a team
     */
    public function buildTeamUrl(string $teamName, string $gameType): string
    {
        $path = $this->gamePaths[$gameType] ?? '/valorant/';

        // Normalize team name
        $normalizedName = $this->normalizeTeamName($teamName);

        // Replace spaces with underscores (Liquipedia convention)
        $encodedName = str_replace(' ', '_', $normalizedName);

        return $path . urlencode($encodedName);
    }

    /**
     * Try fetching with multiple URL variants
     */
    protected function tryFetchWithVariants(string $teamName, string $gameType): ?array
    {
        $variants = [
            $teamName,                                    // Original
            $this->normalizeTeamName($teamName),          // Normalized
            ucwords(strtolower($teamName)),               // Title Case
            strtoupper($teamName),                        // UPPERCASE
            ucfirst(strtolower($teamName)),               // First cap only
        ];

        // Remove duplicates
        $variants = array_unique($variants);

        foreach ($variants as $variant) {
            $url = $this->buildTeamUrl($variant, $gameType);
            $html = $this->fetch($url);

            if (!empty($html)) {
                return ['html' => $html, 'url' => $url, 'name' => $variant];
            }

            // Small delay between attempts
            usleep(500000); // 0.5 seconds
        }

        return null;
    }

    /**
     * Scrape team information from Liquipedia
     */
    public function scrapeTeam(string $teamName, string $gameType): ?array
    {
        // Try to fetch with multiple URL variants
        $result = $this->tryFetchWithVariants($teamName, $gameType);

        if (!$result) {
            error_log("TeamScraper: All URL variants failed for team '$teamName' ($gameType)");
            return null;
        }

        $html = $result['html'];
        $actualUrl = $result['url'];
        $actualName = $result['name'];

        $crawler = new Crawler($html);

        // Extract the canonical team name from the page title if available
        $pageTitle = $this->extractPageTitle($crawler);
        $finalName = $pageTitle ?: $actualName;

        return [
            'name' => $finalName,
            'game_type' => $gameType,
            'region' => $this->extractRegion($crawler),
            'country' => $this->extractCountry($crawler),
            'logo_url' => $this->extractLogo($crawler),
            'description' => $this->extractDescription($crawler),
            'liquipedia_url' => $this->baseUrl . $actualUrl,
            'roster' => $this->extractActiveRoster($crawler, $gameType),
            'results' => $this->extractResults($crawler)
        ];
    }

    /**
     * Extract page title from the page
     */
    protected function extractPageTitle(Crawler $crawler): ?string
    {
        try {
            // Try main page title
            $titleNode = $crawler->filter('#firstHeading, .page-header__title');
            if ($titleNode->count() > 0) {
                return trim($titleNode->first()->text());
            }
        } catch (Exception $e) {
            // Ignore
        }
        return null;
    }

    /**
     * Extract team region from infobox
     */
    protected function extractRegion(Crawler $crawler): string
    {
        try {
            // Try to find region in infobox-cell-2 (common Liquipedia structure)
            $infobox = $crawler->filter('.infobox-cell-2');
            foreach ($infobox as $cell) {
                $text = trim($cell->textContent);
                if (in_array($text, ['Americas', 'EMEA', 'Pacific', 'Europe', 'North America', 'Asia', 'China', 'Korea'])) {
                    // Map to standardized regions
                    $regionMap = [
                        'Europe' => 'EMEA',
                        'North America' => 'Americas',
                        'Asia' => 'Pacific',
                        'China' => 'Pacific',
                        'Korea' => 'Pacific'
                    ];
                    return $regionMap[$text] ?? $text;
                }
            }

            // Fallback: check category links
            $categories = $crawler->filter('a[href*="Category:"]');
            foreach ($categories as $cat) {
                $href = $cat->getAttribute('href');
                if (str_contains($href, 'North_America') || str_contains($href, 'United_States') || str_contains($href, 'Canada') || str_contains($href, 'Brazil'))
                    return 'Americas';
                if (str_contains($href, 'South_America') || str_contains($href, 'Latin_America'))
                    return 'Americas';
                if (str_contains($href, 'Europe') || str_contains($href, 'United_Kingdom') || str_contains($href, 'Germany') || str_contains($href, 'France') || str_contains($href, 'Spain'))
                    return 'EMEA';
                if (str_contains($href, 'Korea') || str_contains($href, 'Japan') || str_contains($href, 'China') || str_contains($href, 'Asia'))
                    return 'Pacific';
            }
        } catch (Exception $e) {
            error_log("Error extracting region: " . $e->getMessage());
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
            $flagNode = $crawler->filter('.infobox a[href*="Category:"]');
            foreach ($flagNode as $node) {
                if ($node instanceof \DOMElement) {
                    $href = $node->getAttribute('href');
                    if (preg_match('/Category:([A-Za-z_]+)$/', $href, $matches)) {
                        $country = str_replace('_', ' ', $matches[1]);
                        // Common countries
                        $validCountries = [
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
                            'Netherlands',
                            'Australia'
                        ];
                        if (in_array($country, $validCountries)) {
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
     * Extract team logo URL
     */
    protected function extractLogo(Crawler $crawler): ?string
    {
        try {
            // Try multiple selectors for logo
            $selectors = [
                '.infobox-image img',
                '.team-template-image img',
                '.floatleft img',
                '.infobox img'
            ];

            foreach ($selectors as $selector) {
                $logoNode = $crawler->filter($selector);
                if ($logoNode->count() > 0) {
                    $src = $logoNode->first()->attr('src');
                    if ($src && !str_contains($src, 'Placeholder') && !str_contains($src, 'Icon_')) {
                        // Handle relative URLs
                        if (!str_starts_with($src, 'http')) {
                            return 'https://liquipedia.net' . $src;
                        }
                        return $src;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error extracting logo: " . $e->getMessage());
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
     * Extract ACTIVE roster only from team page
     * This filters out former players, stand-ins, and organization staff
     */
    protected function extractActiveRoster(Crawler $crawler, string $gameType): array
    {
        $players = [];

        try {
            // Find the "Active" section specifically
            // Liquipedia uses headings with "Active" text followed by roster tables

            // Method 1: Look for roster-card within Active section
            $activeSection = $crawler->filter('#Active, .mw-headline:contains("Active")');

            if ($activeSection->count() > 0) {
                // Get the parent section and find the next roster table/card
                $parent = $activeSection->first()->ancestors()->filter('.roster-section, .mw-parser-output > div, section')->first();
                if ($parent->count() > 0) {
                    $rosterCards = $parent->filter('.roster-card .player-row, .roster-card tr');
                    if ($rosterCards->count() > 0) {
                        $rosterCards->each(function (Crawler $row) use (&$players, $gameType) {
                            $this->extractPlayerFromRow($row, $players, $gameType);
                        });
                    }
                }
            }

            // Method 2: Use the first roster-card (usually Active roster)
            if (empty($players)) {
                $firstRosterCard = $crawler->filter('.roster-card')->first();
                if ($firstRosterCard->count() > 0) {
                    $firstRosterCard->filter('.player-row, tr')->each(function (Crawler $row) use (&$players, $gameType) {
                        $this->extractPlayerFromRow($row, $players, $gameType);
                    });
                }
            }

            // Method 3: Look for players in the infobox (main roster)
            if (empty($players)) {
                $infoboxPlayers = $crawler->filter('.infobox a[href*="/' . ($gameType === 'lol' ? 'leagueoflegends' : ($gameType === 'cs2' ? 'counterstrike' : $gameType)) . '/"]');
                $count = 0;

                foreach ($infoboxPlayers as $node) {
                    if ($count >= 7)
                        break; // Limit to typical roster size

                    if ($node instanceof \DOMElement) {
                        $text = trim($node->textContent);
                        $href = $node->getAttribute('href');

                        // Filter out non-player links
                        if (
                            strlen($text) > 1 && strlen($text) < 30 &&
                            !str_contains($href, 'Tournament') &&
                            !str_contains($href, 'Match:') &&
                            !str_contains($href, 'Category:') &&
                            !str_contains($text, ':') &&
                            !is_numeric($text)
                        ) {
                            // Check for duplicates
                            $exists = array_filter($players, fn($p) => $p['nickname'] === $text);
                            if (empty($exists)) {
                                $players[] = [
                                    'nickname' => $text,
                                    'role' => $this->detectRoleFromContext($node, $gameType),
                                    'country' => null,
                                    'liquipedia_url' => $this->baseUrl . $href
                                ];
                                $count++;
                            }
                        }
                    }
                }
            }

            // Method 4: Parse wikitables but only if they look like Active roster
            if (empty($players)) {
                $tables = $crawler->filter('table.wikitable');
                $tables->each(function (Crawler $table, int $tableIndex) use (&$players, $gameType) {
                    // Only check first 2 tables (usually Active, then Former)
                    if ($tableIndex > 1 || count($players) >= 7)
                        return;

                    // Check if this table follows an "Active" heading
                    $isActiveTable = true; // Assume first table is active

                    if ($isActiveTable) {
                        $table->filter('tr')->each(function (Crawler $row) use (&$players, $gameType) {
                            if (count($players) < 7) { // Limit roster size
                                $this->extractPlayerFromRow($row, $players, $gameType);
                            }
                        });
                    }
                });
            }

        } catch (Exception $e) {
            error_log("Error extracting roster: " . $e->getMessage());
        }

        // Limit to maximum typical roster size
        return array_slice($players, 0, 7);
    }

    /**
     * Detect player role from surrounding context
     */
    protected function detectRoleFromContext(\DOMElement $node, string $gameType): ?string
    {
        try {
            // Check parent elements for role information
            $parent = $node->parentNode;
            $maxLevels = 3;

            while ($parent && $maxLevels > 0) {
                if ($parent instanceof \DOMElement) {
                    $text = strtolower($parent->textContent);
                    $roles = $this->rolesByGame[$gameType] ?? [];

                    foreach ($roles as $role) {
                        if (str_contains($text, $role)) {
                            return ucfirst($role);
                        }
                    }

                    // Check for role images
                    $imgs = $parent->getElementsByTagName('img');
                    foreach ($imgs as $img) {
                        $alt = strtolower($img->getAttribute('alt') ?? '');
                        $title = strtolower($img->getAttribute('title') ?? '');

                        foreach ($roles as $role) {
                            if (str_contains($alt, $role) || str_contains($title, $role)) {
                                return ucfirst($role);
                            }
                        }
                    }
                }

                $parent = $parent->parentNode;
                $maxLevels--;
            }
        } catch (Exception $e) {
            // Ignore
        }

        return null;
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

                // Validate this is a player link
                if (
                    strlen($name) > 1 && strlen($name) < 30 &&
                    !str_contains($name, ':') &&
                    !is_numeric($name) &&
                    !str_contains($href ?? '', 'Category:') &&
                    !str_contains($href ?? '', 'Tournament')
                ) {
                    // Try to get role
                    $role = null;
                    $cells->each(function (Crawler $cell) use (&$role, $gameType) {
                        $text = strtolower(trim($cell->text()));
                        $roles = $this->rolesByGame[$gameType] ?? [];

                        foreach ($roles as $r) {
                            if (str_contains($text, $r) || $text === $r) {
                                $role = ucfirst($r);
                                return;
                            }
                        }

                        // Check for role in images
                        $cell->filter('img')->each(function (Crawler $img) use (&$role, $gameType) {
                            $alt = strtolower($img->attr('alt') ?? '');
                            $title = strtolower($img->attr('title') ?? '');
                            $roles = $this->rolesByGame[$gameType] ?? [];

                            foreach ($roles as $r) {
                                if (str_contains($alt, $r) || str_contains($title, $r)) {
                                    $role = ucfirst($r);
                                    return;
                                }
                            }
                        });
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
            // Look for results section by heading
            $resultsSection = $crawler->filter('#Results, .mw-headline:contains("Results")');

            if ($resultsSection->count() > 0) {
                // Find the next table after Results heading
                $allContent = $crawler->filter('.mw-parser-output');

                // Alternative: Look for achievement/results tables
                $tables = $crawler->filter('table.wikitable');

                foreach ($tables as $tableIndex => $tableNode) {
                    if (count($results) >= 5)
                        break;

                    $table = new Crawler($tableNode);

                    // Check if this looks like a results table
                    $headers = $table->filter('th')->each(fn($th) => strtolower(trim($th->text())));

                    $hasPlacement = in_array('placement', $headers) || in_array('place', $headers) || in_array('result', $headers);
                    $hasTournament = in_array('tournament', $headers) || in_array('event', $headers);

                    if ($hasPlacement && $hasTournament) {
                        $table->filter('tr')->each(function (Crawler $row, int $i) use (&$results) {
                            if ($i === 0 || count($results) >= 5)
                                return; // Skip header, limit to 5

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
                }
            }

            // Fallback: Look for infobox results (common in some pages)
            if (empty($results)) {
                $infoboxResults = $crawler->filter('.infobox-center table.wikitable tr');
                $infoboxResults->each(function (Crawler $row, int $i) use (&$results) {
                    if ($i === 0 || count($results) >= 5)
                        return;

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

        } catch (Exception $e) {
            error_log("Error extracting results: " . $e->getMessage());
        }

        return array_slice($results, 0, 5);
    }
}
