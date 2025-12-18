<?php

declare(strict_types=1);

namespace App\Classes;

use App\Interfaces\ScraperInterface;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

/**
 * VLR.gg Scraper - Complementa datos de Liquipedia con estadísticas avanzadas
 */
class VlrScraper implements ScraperInterface
{
    protected Client $client;
    protected string $baseUrl = 'https://www.vlr.gg';

    // Rate limiting
    protected int $requestDelayMs = 2000;
    protected ?float $lastRequestTime = null;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 15.0,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => 'https://www.google.com/',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ],
            'verify' => false
        ]);
    }

    public function getGameType(): string
    {
        return 'valorant';
    }

    /**
     * Aplica rate limiting entre requests
     */
    protected function applyRateLimit(): void
    {
        if ($this->lastRequestTime !== null) {
            $elapsed = (microtime(true) - $this->lastRequestTime) * 1000;
            if ($elapsed < $this->requestDelayMs) {
                usleep(($this->requestDelayMs - $elapsed) * 1000);
            }
        }
        $this->lastRequestTime = microtime(true);
    }

    /**
     * Fetch HTML con rate limiting
     */
    protected function fetch(string $uri): string
    {
        $this->applyRateLimit();

        try {
            $response = $this->client->request('GET', $uri);
            return (string) $response->getBody();
        } catch (Exception $e) {
            error_log("VlrScraper fetch error: " . $e->getMessage());
            return '';
        }
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
            'players' => []
        ];

        // Extraer nombres de equipos
        $teamNodes = $crawler->filter('.match-header-vs .wf-title-med');
        $team1Name = $teamNodes->count() > 0 ? trim($teamNodes->eq(0)->text()) : 'Team1';
        $team2Name = $teamNodes->count() > 1 ? trim($teamNodes->eq(1)->text()) : 'Team2';

        // Mapas jugados
        $crawler->filter('.vm-stats-game')->each(function (Crawler $mapNode) use (&$details) {
            $mapName = 'Unknown';
            $mapHeader = $mapNode->filter('.map span');
            if ($mapHeader->count()) {
                $mapName = trim($mapHeader->first()->text());
            }

            $scores = $mapNode->filter('.score');
            $score1 = $scores->count() > 0 ? trim($scores->eq(0)->text()) : '0';
            $score2 = $scores->count() > 1 ? trim($scores->eq(1)->text()) : '0';

            $details['maps'][] = [
                'name' => $mapName,
                'score1' => $score1,
                'score2' => $score2
            ];
        });

        // Estadísticas de jugadores (tabla overview)
        $playerIndex = 0;
        $crawler->filter('table.wf-table-inset.mod-overview tbody tr')->each(function (Crawler $row) use (&$details, &$playerIndex, $team1Name, $team2Name) {
            try {
                $cells = $row->filter('td');
                if ($cells->count() < 6)
                    return;

                // Determinar equipo basado en posición (primeros 5 = team1, siguientes = team2)
                $teamName = ($playerIndex < 5) ? $team1Name : $team2Name;
                $playerIndex++;

                // Nombre del jugador
                $playerCell = $cells->eq(0);
                $playerLink = $playerCell->filter('a');
                $playerName = $playerLink->count() ? trim($playerLink->text()) : trim($playerCell->text());

                // Agente
                $agentImg = $playerCell->filter('img.mod-agent');
                $agent = $agentImg->count() ? ($agentImg->attr('alt') ?? $agentImg->attr('title') ?? '') : '';

                // Stats (orden: Player, R (rating), ACS, K, D, A, +/-, KAST, ADR, HS%, FK, FD)
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
            } catch (Exception $e) {
                // Skip malformed row
            }
        });

        return $details;
    }

    /**
     * Busca un partido en VLR.gg por nombres de equipo
     * Retorna el vlr_match_id si encuentra coincidencia
     */
    public function findMatchByTeams(string $team1, string $team2, ?string $date = null): ?string
    {
        // Normalizar nombres para comparación
        $team1 = strtolower(trim($team1));
        $team2 = strtolower(trim($team2));

        // Buscar en resultados recientes
        $results = $this->scrapeResults();

        foreach ($results as $match) {
            $matchTeam1 = strtolower(trim($match['team1']));
            $matchTeam2 = strtolower(trim($match['team2']));

            // Comparación flexible (contiene o igual)
            $t1Match = ($matchTeam1 === $team1 || str_contains($matchTeam1, $team1) || str_contains($team1, $matchTeam1));
            $t2Match = ($matchTeam2 === $team2 || str_contains($matchTeam2, $team2) || str_contains($team2, $matchTeam2));

            // También en orden inverso
            $t1Inv = ($matchTeam1 === $team2 || str_contains($matchTeam1, $team2) || str_contains($team2, $matchTeam1));
            $t2Inv = ($matchTeam2 === $team1 || str_contains($matchTeam2, $team1) || str_contains($team1, $matchTeam2));

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
}
