<?php

declare(strict_types=1);

namespace App\Classes;

use App\Interfaces\ScraperInterface;
use App\Traits\AntiBlockingTrait;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

/**
 * VLR.gg Scraper - Complementa datos de Liquipedia con estadísticas avanzadas
 */
class VlrScraper implements ScraperInterface
{
    use AntiBlockingTrait;

    protected Client $client;
    protected string $baseUrl = 'https://www.vlr.gg';

    public function __construct()
    {
        // Configuración específica para VLR.gg
        $this->baseDelayMs = 2500;
        $this->jitterFactor = 0.3;
        $this->maxRetries = 4;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 15.0,
            'verify' => false,
            'cookies' => true
        ]);
    }

    public function getGameType(): string
    {
        return 'valorant';
    }

    /**
     * Normalize text for comparison (remove accents and special chars)
     * This allows matching "KRU" with "KRÜ", "Leviatan" with "LEVIATÁN", etc.
     */
    protected function normalizeForComparison(string $text): string
    {
        $text = strtolower(trim($text));

        // Manual accent mapping for common characters
        $accentMap = [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            'Á' => 'a',
            'À' => 'a',
            'Ä' => 'a',
            'Â' => 'a',
            'Ã' => 'a',
            'É' => 'e',
            'È' => 'e',
            'Ë' => 'e',
            'Ê' => 'e',
            'Í' => 'i',
            'Ì' => 'i',
            'Ï' => 'i',
            'Î' => 'i',
            'Ó' => 'o',
            'Ò' => 'o',
            'Ö' => 'o',
            'Ô' => 'o',
            'Õ' => 'o',
            'Ú' => 'u',
            'Ù' => 'u',
            'Ü' => 'u',
            'Û' => 'u',
            'Ñ' => 'n',
            'Ç' => 'c',
        ];

        $text = strtr($text, $accentMap);

        // Remove common suffixes
        $text = preg_replace('/(\s*)esports?$/i', '', $text);
        $text = preg_replace('/(\s*)gaming$/i', '', $text);
        $text = preg_replace('/(\s*)team$/i', '', $text);

        // Remove extra spaces
        $text = trim($text);

        return $text;
    }

    /**
     * Fetch HTML con rate limiting inteligente y reintentos
     * 
     * @param string $uri URI relativa a VLR.gg
     * @return string HTML content o cadena vacía si falla
     */
    protected function fetch(string $uri): string
    {
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            $this->applySmartRateLimit();

            try {
                $response = $this->client->request('GET', $uri, [
                    'headers' => $this->getRandomHeaders()
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 200) {
                    $this->registerSuccess();
                    return (string) $response->getBody();
                }

                error_log("VlrScraper: Unexpected status code {$statusCode} for {$uri}");
                $this->registerFailure();
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $this->registerFailure();

                if ($statusCode === 429 || $statusCode === 403) {
                    error_log("VlrScraper: Rate limited ({$statusCode}) for {$uri}, attempt {$attempt}, backing off...");
                } else {
                    error_log("VlrScraper: Client error ({$statusCode}) for {$uri}: " . $e->getMessage());
                }
            } catch (Exception $e) {
                $this->registerFailure();
                error_log("VlrScraper fetch error for {$uri}: " . $e->getMessage());
            }

            $attempt++;

            if (!$this->shouldRetry()) {
                error_log("VlrScraper: Max retries reached for {$uri}");
                break;
            }
        }

        return '';
    }

    /**
     * Scrape lista de partidos desde VLR.gg
     */
    public function scrapeMatches(): array
    {
        $html = $this->fetch('/matches');

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $matches = [];

        $crawler->filter('a.match-item, a.wf-module-item.match-item')->each(function (Crawler $node) use (&$matches) {
            try {
                // Equipos
                $teamNodes = $node->filter('.match-item-vs-team-name');
                $team1 = $teamNodes->count() > 0 ? trim($teamNodes->eq(0)->text()) : 'TBD';
                $team2 = $teamNodes->count() > 1 ? trim($teamNodes->eq(1)->text()) : 'TBD';

                // Scores (si completado)
                $scoreNodes = $node->filter('.match-item-vs-team-score');
                $score1 = null;
                $score2 = null;
                if ($scoreNodes->count() >= 2) {
                    $s1 = trim($scoreNodes->eq(0)->text());
                    $s2 = trim($scoreNodes->eq(1)->text());
                    $score1 = is_numeric($s1) ? (int) $s1 : null;
                    $score2 = is_numeric($s2) ? (int) $s2 : null;
                }

                // Estado y tiempo
                $etaNode = $node->filter('.ml-eta, .match-item-time');
                $eta = $etaNode->count() ? trim($etaNode->text()) : '';

                $statusNode = $node->filter('.ml-status');
                $status = 'upcoming';
                if ($statusNode->count() && stripos($statusNode->text(), 'LIVE') !== false) {
                    $status = 'live';
                } elseif ($score1 !== null && $score2 !== null) {
                    $status = 'completed';
                }

                // Torneo
                $tournamentNode = $node->filter('.match-item-event-series, .match-item-event');
                $tournament = $tournamentNode->count() ? trim($tournamentNode->text()) : 'Unknown';

                // URL del partido
                $href = $node->attr('href');
                $matchUrl = $href ? $this->baseUrl . $href : null;

                // Extraer VLR match ID del href (ej: /123456/team1-vs-team2)
                $vlrMatchId = null;
                if (preg_match('/^\/(\d+)\//', $href, $m)) {
                    $vlrMatchId = $m[1];
                }

                if ($team1 !== 'TBD' || $team2 !== 'TBD') {
                    $matches[] = [
                        'team1' => $team1,
                        'team2' => $team2,
                        'tournament' => $tournament,
                        'region' => $this->detectRegion($tournament),
                        'time' => date('Y-m-d H:i:s'), // VLR no siempre tiene timestamp exacto
                        'game_type' => 'valorant',
                        'team1_score' => $score1,
                        'team2_score' => $score2,
                        'match_status' => $status,
                        'match_url' => $matchUrl,
                        'vlr_match_id' => $vlrMatchId,
                        'match_importance' => $this->calculateImportance($tournament)
                    ];
                }
            } catch (Exception $e) {
                // Skip malformed
            }
        });

        return $matches;
    }

    /**
     * Scrape resultados completados desde VLR.gg
     */
    public function scrapeResults(): array
    {
        $html = $this->fetch('/matches/results');

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $matches = [];

        $crawler->filter('a.match-item, a.wf-module-item.match-item')->each(function (Crawler $node) use (&$matches) {
            try {
                $teamNodes = $node->filter('.match-item-vs-team-name');
                $team1 = $teamNodes->count() > 0 ? trim($teamNodes->eq(0)->text()) : 'TBD';
                $team2 = $teamNodes->count() > 1 ? trim($teamNodes->eq(1)->text()) : 'TBD';

                $scoreNodes = $node->filter('.match-item-vs-team-score');
                $score1 = $scoreNodes->count() > 0 ? (int) trim($scoreNodes->eq(0)->text()) : null;
                $score2 = $scoreNodes->count() > 1 ? (int) trim($scoreNodes->eq(1)->text()) : null;

                $tournamentNode = $node->filter('.match-item-event-series, .match-item-event');
                $tournament = $tournamentNode->count() ? trim($tournamentNode->text()) : 'Unknown';

                $href = $node->attr('href');
                $vlrMatchId = null;
                if (preg_match('/^\/(\d+)\//', $href, $m)) {
                    $vlrMatchId = $m[1];
                }

                if ($team1 !== 'TBD' || $team2 !== 'TBD') {
                    $matches[] = [
                        'team1' => $team1,
                        'team2' => $team2,
                        'tournament' => $tournament,
                        'region' => $this->detectRegion($tournament),
                        'time' => date('Y-m-d H:i:s'),
                        'game_type' => 'valorant',
                        'team1_score' => $score1,
                        'team2_score' => $score2,
                        'match_status' => 'completed',
                        'match_url' => $this->baseUrl . $href,
                        'vlr_match_id' => $vlrMatchId,
                        'match_importance' => $this->calculateImportance($tournament)
                    ];
                }
            } catch (Exception $e) {
                // Skip
            }
        });

        return $matches;
    }

    /**
     * Scrape estadísticas detalladas de un partido
     * Ahora extrae stats por mapa además de las overall
     * 
     * @return array ['maps' => [...], 'players' => [...], 'players_by_map' => ['Ascent' => [...], ...]]
     */
    public function scrapeMatchDetails(string $vlrMatchId): array
    {
        $html = $this->fetch('/' . $vlrMatchId);

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $details = [
            'vlr_match_id' => $vlrMatchId,
            'maps' => [],
            'players' => [],           // Stats overall (agregadas)
            'players_by_map' => []     // Stats por mapa
        ];

        // Extraer nombres de equipos
        $teamNodes = $crawler->filter('.match-header-vs .wf-title-med');
        $team1Name = $teamNodes->count() > 0 ? trim($teamNodes->eq(0)->text()) : 'Team1';
        $team2Name = $teamNodes->count() > 1 ? trim($teamNodes->eq(1)->text()) : 'Team2';

        // Iterar sobre cada mapa/game
        $crawler->filter('.vm-stats-game')->each(function (Crawler $mapNode) use (&$details, $team1Name, $team2Name) {
            // Obtener nombre del mapa
            $mapName = 'Unknown';
            $mapHeader = $mapNode->filter('.map span');
            if ($mapHeader->count()) {
                $mapName = trim($mapHeader->first()->text());
                // Limpiar nombre (a veces incluye "PICK" o números)
                $mapName = preg_replace('/\s*(PICK|BAN|\d+)$/i', '', $mapName);
                $mapName = trim($mapName);
            }

            // Score del mapa
            $scores = $mapNode->filter('.score');
            $score1 = $scores->count() > 0 ? trim($scores->eq(0)->text()) : '0';
            $score2 = $scores->count() > 1 ? trim($scores->eq(1)->text()) : '0';

            // Solo añadir si el mapa tiene nombre válido
            if ($mapName !== 'Unknown' && $mapName !== '' && $mapName !== 'All Maps') {
                $details['maps'][] = [
                    'name' => $mapName,
                    'score1' => $score1,
                    'score2' => $score2
                ];

                // Extraer stats de jugadores para ESTE mapa
                $mapPlayers = [];
                $playerIndex = 0;

                $mapNode->filter('table.wf-table-inset tbody tr')->each(function (Crawler $row) use (&$mapPlayers, &$playerIndex, $team1Name, $team2Name, $mapName) {
                    try {
                        $cells = $row->filter('td');
                        if ($cells->count() < 6)
                            return;

                        $teamName = ($playerIndex < 5) ? $team1Name : $team2Name;
                        $playerIndex++;

                        $playerCell = $cells->eq(0);
                        $playerLink = $playerCell->filter('a');
                        $playerName = $playerLink->count() ? trim($playerLink->text()) : trim($playerCell->text());

                        // Try multiple selectors for agent image
                        $agent = '';
                        $agentImg = $playerCell->filter('img.mod-agent');
                        if (!$agentImg->count()) {
                            $agentImg = $playerCell->filter('img'); // Any image in player cell
                        }
                        if (!$agentImg->count() && $cells->count() > 1) {
                            $agentImg = $cells->eq(1)->filter('img'); // Check second cell
                        }
                        if ($agentImg->count()) {
                            $agent = $agentImg->attr('alt') ?? $agentImg->attr('title') ?? '';
                            // Clean agent name
                            $agent = preg_replace('/\s*(icon|logo|image).*$/i', '', $agent);
                        }

                        $stats = $cells->each(fn($td) => trim($td->text()));

                        $acs = isset($stats[2]) && is_numeric($stats[2]) ? (int) $stats[2] : null;
                        $kills = isset($stats[3]) ? (int) preg_replace('/\D/', '', $stats[3]) : 0;
                        $deaths = isset($stats[4]) ? (int) preg_replace('/\D/', '', $stats[4]) : 0;
                        $assists = isset($stats[5]) ? (int) preg_replace('/\D/', '', $stats[5]) : 0;
                        $kast = isset($stats[7]) ? (float) str_replace('%', '', $stats[7]) : null;
                        $adr = isset($stats[8]) ? (float) $stats[8] : null;
                        $hsPercent = isset($stats[9]) ? (float) str_replace('%', '', $stats[9]) : null;
                        $firstBloods = isset($stats[10]) ? (int) $stats[10] : null;
                        $firstDeaths = isset($stats[11]) ? (int) $stats[11] : null;

                        if (!empty($playerName) && $playerName !== 'Player') {
                            $mapPlayers[] = [
                                'name' => $playerName,
                                'team' => $teamName,
                                'agent' => $agent,
                                'kills' => $kills,
                                'deaths' => $deaths,
                                'assists' => $assists,
                                'acs' => $acs,
                                'adr' => $adr,
                                'kast' => $kast,
                                'hs_percent' => $hsPercent,
                                'first_bloods' => $firstBloods,
                                'first_deaths' => $firstDeaths,
                                'data_source' => 'vlr'
                            ];
                        }
                    } catch (Exception $e) {
                        // Skip malformed row
                    }
                });

                if (!empty($mapPlayers)) {
                    $details['players_by_map'][$mapName] = $mapPlayers;
                }
            }
        });

        // Extraer stats OVERALL desde la tabla mod-overview (si existe)
        $playerIndex = 0;
        $crawler->filter('table.wf-table-inset.mod-overview tbody tr')->each(function (Crawler $row) use (&$details, &$playerIndex, $team1Name, $team2Name) {
            try {
                $cells = $row->filter('td');
                if ($cells->count() < 6)
                    return;

                $teamName = ($playerIndex < 5) ? $team1Name : $team2Name;
                $playerIndex++;

                $playerCell = $cells->eq(0);
                $playerLink = $playerCell->filter('a');
                $playerName = $playerLink->count() ? trim($playerLink->text()) : trim($playerCell->text());

                // Try multiple selectors for agent image
                $agent = '';
                $agentImg = $playerCell->filter('img.mod-agent');
                if (!$agentImg->count()) {
                    $agentImg = $playerCell->filter('img');
                }
                if (!$agentImg->count() && $cells->count() > 1) {
                    $agentImg = $cells->eq(1)->filter('img');
                }
                if ($agentImg->count()) {
                    $agent = $agentImg->attr('alt') ?? $agentImg->attr('title') ?? '';
                    $agent = preg_replace('/\s*(icon|logo|image).*$/i', '', $agent);
                }

                $stats = $cells->each(fn($td) => trim($td->text()));

                $acs = isset($stats[2]) && is_numeric($stats[2]) ? (int) $stats[2] : null;
                $kills = isset($stats[3]) ? (int) preg_replace('/\D/', '', $stats[3]) : 0;
                $deaths = isset($stats[4]) ? (int) preg_replace('/\D/', '', $stats[4]) : 0;
                $assists = isset($stats[5]) ? (int) preg_replace('/\D/', '', $stats[5]) : 0;
                $kast = isset($stats[7]) ? (float) str_replace('%', '', $stats[7]) : null;
                $adr = isset($stats[8]) ? (float) $stats[8] : null;
                $hsPercent = isset($stats[9]) ? (float) str_replace('%', '', $stats[9]) : null;
                $firstBloods = isset($stats[10]) ? (int) $stats[10] : null;
                $firstDeaths = isset($stats[11]) ? (int) $stats[11] : null;

                if (!empty($playerName) && $playerName !== 'Player') {
                    $details['players'][] = [
                        'name' => $playerName,
                        'team' => $teamName,
                        'agent' => $agent,
                        'kills' => $kills,
                        'deaths' => $deaths,
                        'assists' => $assists,
                        'acs' => $acs,
                        'adr' => $adr,
                        'kast' => $kast,
                        'hs_percent' => $hsPercent,
                        'first_bloods' => $firstBloods,
                        'first_deaths' => $firstDeaths,
                        'data_source' => 'vlr'
                    ];
                }
            } catch (Exception $e) {
                // Skip malformed row
            }
        });

        return $details;
    }

    /**
     * Busca un partido en VLR.gg por nombres de equipo
     * Retorna el vlr_match_id si encuentra coincidencia
     * Usa normalización flexible para comparar nombres con acentos
     */
    public function findMatchByTeams(string $team1, string $team2, ?string $date = null): ?string
    {
        // Normalizar nombres para comparación (elimina acentos y sufijos)
        $team1Norm = $this->normalizeForComparison($team1);
        $team2Norm = $this->normalizeForComparison($team2);

        // Buscar en resultados recientes
        $results = $this->scrapeResults();

        foreach ($results as $match) {
            $matchTeam1Norm = $this->normalizeForComparison($match['team1']);
            $matchTeam2Norm = $this->normalizeForComparison($match['team2']);

            // Comparación flexible (contiene o igual) usando texto normalizado
            $t1Match = ($matchTeam1Norm === $team1Norm || str_contains($matchTeam1Norm, $team1Norm) || str_contains($team1Norm, $matchTeam1Norm));
            $t2Match = ($matchTeam2Norm === $team2Norm || str_contains($matchTeam2Norm, $team2Norm) || str_contains($team2Norm, $matchTeam2Norm));

            // También en orden inverso
            $t1Inv = ($matchTeam1Norm === $team2Norm || str_contains($matchTeam1Norm, $team2Norm) || str_contains($team2Norm, $matchTeam1Norm));
            $t2Inv = ($matchTeam2Norm === $team1Norm || str_contains($matchTeam2Norm, $team1Norm) || str_contains($team1Norm, $matchTeam2Norm));

            if (($t1Match && $t2Match) || ($t1Inv && $t2Inv)) {
                return $match['vlr_match_id'];
            }
        }

        return null;
    }

    /**
     * Detecta región basándose en el nombre del torneo
     */
    protected function detectRegion(string $tournament): string
    {
        $tournament = strtoupper($tournament);

        if (
            str_contains($tournament, 'AMERICAS') || str_contains($tournament, 'NORTH AMERICA') ||
            str_contains($tournament, 'BRAZIL') || str_contains($tournament, 'LATAM')
        ) {
            return 'Americas';
        }

        if (
            str_contains($tournament, 'PACIFIC') || str_contains($tournament, 'APAC') ||
            str_contains($tournament, 'KOREA') || str_contains($tournament, 'JAPAN') ||
            str_contains($tournament, 'CHINA') || str_contains($tournament, 'SEA')
        ) {
            return 'Pacific';
        }

        if (
            str_contains($tournament, 'EMEA') || str_contains($tournament, 'EUROPE') ||
            str_contains($tournament, 'TURKEY') || str_contains($tournament, 'CIS')
        ) {
            return 'EMEA';
        }

        if (
            str_contains($tournament, 'CHAMPIONS') || str_contains($tournament, 'MASTERS') ||
            str_contains($tournament, 'LOCK//IN')
        ) {
            return 'International';
        }

        return 'Other';
    }

    /**
     * Calcula importancia del partido
     */
    protected function calculateImportance(string $tournament): int
    {
        $tournament = strtoupper($tournament);
        $score = 30;

        if (str_contains($tournament, 'CHAMPIONS') || str_contains($tournament, 'MASTERS')) {
            $score += 70;
        } elseif (str_contains($tournament, 'VCT') || str_contains($tournament, 'CHALLENGERS')) {
            $score += 40;
        } elseif (str_contains($tournament, 'GAME CHANGERS')) {
            $score += 30;
        }

        if (str_contains($tournament, 'FINAL') || str_contains($tournament, 'GRAND')) {
            $score += 30;
        } elseif (str_contains($tournament, 'PLAYOFF')) {
            $score += 20;
        }

        return min($score, 200);
    }

    /**
     * Scrape teams from VLR.gg rankings page
     * This discovers tier 2+ teams that may not appear in main match lists
     * 
     * @param string|null $region Optional region filter (europe, north-america, brazil, asia-pacific, korea, china, japan, la-s, la-n, oceania, mena)
     * @return array List of teams with name, country, vlr_id, rating
     */
    public function scrapeRankings(?string $region = null): array
    {
        $url = '/rankings';
        if ($region) {
            $url .= '/' . $region;
        }

        $html = $this->fetch($url);
        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $teams = [];

        // Parse ranking rows - each team is in a ranking item
        $crawler->filter('.rank-item, .wf-module-item.rank-item, a[href*="/team/"]')->each(function (Crawler $node) use (&$teams) {
            try {
                // Get team link
                $href = $node->attr('href');
                if (!$href || !str_contains($href, '/team/')) {
                    // Try to find link inside
                    $teamLink = $node->filter('a[href*="/team/"]');
                    if ($teamLink->count()) {
                        $href = $teamLink->first()->attr('href');
                    } else {
                        return;
                    }
                }

                // Extract VLR team ID from URL
                $vlrTeamId = null;
                if (preg_match('/\/team\/(\d+)\//', $href, $m)) {
                    $vlrTeamId = $m[1];
                }

                // Get team name
                $teamName = '';
                $nameNode = $node->filter('.rank-item-team-name, .wf-title, .text-of');
                if ($nameNode->count()) {
                    $teamName = trim($nameNode->first()->text());
                } else {
                    // Try text content
                    $teamName = trim($node->text());
                    // Clean up - remove rating numbers
                    $teamName = preg_replace('/\d{3,4}$/', '', $teamName);
                    $teamName = preg_replace('/^\d+/', '', $teamName);
                    $teamName = trim($teamName);
                }

                // Get country/region
                $country = '';
                $countryNode = $node->filter('.rank-item-team-country, .ge-text-light');
                if ($countryNode->count()) {
                    $country = trim($countryNode->first()->text());
                }

                // Get rating
                $rating = null;
                $ratingNode = $node->filter('.rank-item-rating, .rating');
                if ($ratingNode->count()) {
                    $rating = (int) trim($ratingNode->first()->text());
                }

                if (!empty($teamName) && $teamName !== 'TBD' && strlen($teamName) > 1) {
                    // Avoid duplicates
                    $exists = array_filter($teams, fn($t) => $t['name'] === $teamName);
                    if (empty($exists)) {
                        $teams[] = [
                            'name' => $teamName,
                            'country' => $country ?: null,
                            'vlr_team_id' => $vlrTeamId,
                            'rating' => $rating,
                            'vlr_url' => $this->baseUrl . $href,
                            'game_type' => 'valorant',
                            'region' => $this->detectRegionFromCountry($country)
                        ];
                    }
                }
            } catch (Exception $e) {
                // Skip
            }
        });

        return $teams;
    }

    /**
     * Scrape all teams from multiple VLR.gg ranking regions
     * Useful for bulk importing tier 2 teams
     * 
     * @param array $regions List of regions to scrape, or null for all
     * @return array All discovered teams
     */
    public function scrapeAllRankings(?array $regions = null): array
    {
        $allRegions = [
            'europe',
            'north-america',
            'brazil',
            'asia-pacific',
            'korea',
            'china',
            'japan',
            'la-s',
            'la-n',
            'oceania',
            'mena'
        ];

        $regionsToScrape = $regions ?? $allRegions;
        $allTeams = [];

        foreach ($regionsToScrape as $region) {
            $teams = $this->scrapeRankings($region);

            foreach ($teams as $team) {
                // Check for duplicates by name
                $exists = array_filter(
                    $allTeams,
                    fn($t) =>
                    $this->normalizeForComparison($t['name']) === $this->normalizeForComparison($team['name'])
                );

                if (empty($exists)) {
                    $allTeams[] = $team;
                }
            }

            // Rate limiting between regions
            sleep(2);
        }

        return $allTeams;
    }

    /**
     * Detect region from country name
     */
    protected function detectRegionFromCountry(string $country): string
    {
        $country = strtolower($country);

        // Americas
        $americas = ['united states', 'canada', 'brazil', 'argentina', 'chile', 'mexico', 'peru', 'colombia', 'uruguay'];
        foreach ($americas as $c) {
            if (str_contains($country, $c)) return 'Americas';
        }

        // Pacific
        $pacific = ['korea', 'japan', 'china', 'singapore', 'indonesia', 'thailand', 'vietnam', 'philippines', 'india', 'taiwan', 'hong kong', 'malaysia', 'australia'];
        foreach ($pacific as $c) {
            if (str_contains($country, $c)) return 'Pacific';
        }

        // EMEA
        $emea = ['europe', 'germany', 'france', 'spain', 'united kingdom', 'turkey', 'russia', 'poland', 'sweden', 'denmark', 'finland', 'norway', 'italy', 'netherlands', 'portugal', 'czech', 'ukraine', 'israel', 'saudi', 'uae', 'morocco', 'egypt'];
        foreach ($emea as $c) {
            if (str_contains($country, $c)) return 'EMEA';
        }

        return 'Other';
    }
}
