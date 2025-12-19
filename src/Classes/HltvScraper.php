<?php

declare(strict_types=1);

namespace App\Classes;

use App\Interfaces\ScraperInterface;
use App\Traits\AntiBlockingTrait;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Exception;

/**
 * HLTV.org Scraper - Complementa datos de Liquipedia con estadísticas avanzadas para CS2
 * 
 * @package App\Classes
 */
class HltvScraper implements ScraperInterface
{
    use AntiBlockingTrait;

    protected Client $client;
    protected string $baseUrl = 'https://www.hltv.org';

    public function __construct()
    {
        // Configuración específica para HLTV (más restrictivo)
        $this->baseDelayMs = 3500;
        $this->jitterFactor = 0.35;
        $this->maxRetries = 4;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 20.0,
            'verify' => false,
            'allow_redirects' => true,
            'cookies' => true // Mantener cookies entre requests
        ]);
    }

    /**
     * @return string Game type identifier
     */
    public function getGameType(): string
    {
        return 'cs2';
    }

    /**
     * Fetch HTML con rate limiting inteligente y reintentos
     * 
     * @param string $uri URI relativa a HLTV
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

                // Si el código no es 200 pero tampoco error, registrar y continuar
                error_log("HltvScraper: Unexpected status code {$statusCode} for {$uri}");
                $this->registerFailure();

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $this->registerFailure();

                if ($statusCode === 429 || $statusCode === 403) {
                    error_log("HltvScraper: Rate limited ({$statusCode}) for {$uri}, attempt {$attempt}, backing off...");
                    // El backoff ya está calculado en applySmartRateLimit
                } else {
                    error_log("HltvScraper: Client error ({$statusCode}) for {$uri}: " . $e->getMessage());
                }

            } catch (Exception $e) {
                $this->registerFailure();
                error_log("HltvScraper fetch error for {$uri}: " . $e->getMessage());
            }

            $attempt++;

            if (!$this->shouldRetry()) {
                error_log("HltvScraper: Max retries reached for {$uri}");
                break;
            }
        }

        return '';
    }

    /**
     * Scrape lista de próximos partidos desde HLTV
     * 
     * @return array Lista de partidos
     */
    public function scrapeMatches(): array
    {
        $html = $this->fetch('/matches');

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $matches = [];

        // Partidos en vivo
        $crawler->filter('.liveMatch-container')->each(function (Crawler $node) use (&$matches) {
            try {
                $match = $this->parseMatchNode($node, 'live');
                if ($match) {
                    $matches[] = $match;
                }
            } catch (Exception $e) {
                // Skip malformed
            }
        });

        // Próximos partidos
        $crawler->filter('.upcomingMatch')->each(function (Crawler $node) use (&$matches) {
            try {
                $match = $this->parseMatchNode($node, 'upcoming');
                if ($match) {
                    $matches[] = $match;
                }
            } catch (Exception $e) {
                // Skip malformed
            }
        });

        return $matches;
    }

    /**
     * Parse un nodo de partido individual
     * 
     * @param Crawler $node Nodo DOM del partido
     * @param string $status Estado del partido
     * @return array|null Datos del partido o null si es inválido
     */
    protected function parseMatchNode(Crawler $node, string $status): ?array
    {
        // Nombres de equipos
        $teamNodes = $node->filter('.matchTeamName, .team');
        $team1 = $teamNodes->count() > 0 ? trim($teamNodes->eq(0)->text()) : 'TBD';
        $team2 = $teamNodes->count() > 1 ? trim($teamNodes->eq(1)->text()) : 'TBD';

        if ($team1 === 'TBD' && $team2 === 'TBD') {
            return null;
        }

        // Scores (si el partido está en vivo o completado)
        $score1 = null;
        $score2 = null;
        $scoreNodes = $node->filter('.currentMapScore, .matchTeamScore');
        if ($scoreNodes->count() >= 2) {
            $s1 = trim($scoreNodes->eq(0)->text());
            $s2 = trim($scoreNodes->eq(1)->text());
            $score1 = is_numeric($s1) ? (int) $s1 : null;
            $score2 = is_numeric($s2) ? (int) $s2 : null;
        }

        // Torneo/Evento
        $eventNode = $node->filter('.matchEventName, .matchEventLogo');
        $tournament = $eventNode->count() ? trim($eventNode->attr('title') ?? $eventNode->text()) : 'Unknown';

        // Hora del partido
        $timeNode = $node->filter('.matchTime');
        $timestamp = $timeNode->count() ? $timeNode->attr('data-unix') : null;
        $time = $timestamp ? date('Y-m-d H:i:s', (int) ($timestamp / 1000)) : date('Y-m-d H:i:s');

        // URL del partido
        $href = $node->filter('a.match, a')->count() ? $node->filter('a.match, a')->first()->attr('href') : null;
        $matchUrl = $href ? $this->baseUrl . $href : null;

        // Extraer HLTV match ID
        $hltvMatchId = null;
        if ($href && preg_match('/\/matches\/(\d+)\//', $href, $m)) {
            $hltvMatchId = $m[1];
        }

        // Best of type
        $boNode = $node->filter('.matchMeta, .bestOf');
        $boType = $boNode->count() ? trim($boNode->text()) : null;

        // Stars (importancia)
        $starsNode = $node->filter('.stars i.faded');
        $stars = 5 - $starsNode->count(); // Las estrellas llenas son la importancia

        return [
            'team1' => $team1,
            'team2' => $team2,
            'tournament' => $tournament,
            'region' => $this->detectRegion($tournament),
            'time' => $time,
            'game_type' => 'cs2',
            'team1_score' => $score1,
            'team2_score' => $score2,
            'match_status' => $status,
            'match_url' => $matchUrl,
            'hltv_match_id' => $hltvMatchId,
            'bo_type' => $boType,
            'match_importance' => $this->calculateImportance($tournament, $stars)
        ];
    }

    /**
     * Scrape resultados completados desde HLTV
     * 
     * @return array Lista de resultados
     */
    public function scrapeResults(): array
    {
        $html = $this->fetch('/results');

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $matches = [];

        $crawler->filter('.result-con')->each(function (Crawler $node) use (&$matches) {
            try {
                // Equipos
                $teamNodes = $node->filter('.team');
                $team1 = $teamNodes->count() > 0 ? trim($teamNodes->eq(0)->text()) : 'TBD';
                $team2 = $teamNodes->count() > 1 ? trim($teamNodes->eq(1)->text()) : 'TBD';

                // Scores
                $scoreNodes = $node->filter('.result-score');
                $scoreText = $scoreNodes->count() ? trim($scoreNodes->text()) : '';
                $scores = explode(' - ', str_replace(':', ' - ', $scoreText));
                $score1 = isset($scores[0]) ? (int) trim($scores[0]) : null;
                $score2 = isset($scores[1]) ? (int) trim($scores[1]) : null;

                // Evento
                $eventNode = $node->filter('.event-name, .event img');
                $tournament = $eventNode->count() ?
                    trim($eventNode->attr('title') ?? $eventNode->text()) : 'Unknown';

                // URL
                $href = $node->filter('a.a-reset')->attr('href');
                $matchUrl = $href ? $this->baseUrl . $href : null;

                // HLTV Match ID
                $hltvMatchId = null;
                if ($href && preg_match('/\/matches\/(\d+)\//', $href, $m)) {
                    $hltvMatchId = $m[1];
                }

                if ($team1 !== 'TBD' || $team2 !== 'TBD') {
                    $matches[] = [
                        'team1' => $team1,
                        'team2' => $team2,
                        'tournament' => $tournament,
                        'region' => $this->detectRegion($tournament),
                        'time' => date('Y-m-d H:i:s'),
                        'game_type' => 'cs2',
                        'team1_score' => $score1,
                        'team2_score' => $score2,
                        'match_status' => 'completed',
                        'match_url' => $matchUrl,
                        'hltv_match_id' => $hltvMatchId,
                        'match_importance' => $this->calculateImportance($tournament, 0)
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
     * 
     * @param string $hltvMatchId ID del partido en HLTV
     * @return array Detalles del partido incluyendo estadísticas de jugadores
     */
    public function scrapeMatchDetails(string $hltvMatchId): array
    {
        // HLTV requiere el slug completo, intentamos con la página de stats
        $html = $this->fetch("/matches/{$hltvMatchId}/-");

        if (empty($html)) {
            // Intentar URL alternativa
            $html = $this->fetch("/stats/matches/{$hltvMatchId}/-");
        }

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $details = [
            'hltv_match_id' => $hltvMatchId,
            'maps' => [],
            'players' => []
        ];

        // Nombres de equipos
        $teamNodes = $crawler->filter('.teamName, .team1-gradient, .team2-gradient');
        $team1Name = $teamNodes->count() > 0 ? trim($teamNodes->eq(0)->text()) : 'Team1';
        $team2Name = $teamNodes->count() > 1 ? trim($teamNodes->eq(1)->text()) : 'Team2';

        // Mapas jugados
        $crawler->filter('.mapholder, .map-name-holder')->each(function (Crawler $mapNode) use (&$details) {
            $mapNameNode = $mapNode->filter('.mapname, .map-name');
            $mapName = $mapNameNode->count() ? trim($mapNameNode->text()) : 'Unknown';

            $scoreNodes = $mapNode->filter('.results-team-score');
            $score1 = $scoreNodes->count() > 0 ? trim($scoreNodes->eq(0)->text()) : '0';
            $score2 = $scoreNodes->count() > 1 ? trim($scoreNodes->eq(1)->text()) : '0';

            if ($mapName !== 'Unknown' && $mapName !== 'TBA') {
                $details['maps'][] = [
                    'name' => $mapName,
                    'score1' => $score1,
                    'score2' => $score2
                ];
            }
        });

        // Estadísticas de jugadores (tabla principal)
        $playerIndex = 0;
        $crawler->filter('.stats-table tbody tr, .totalstats tbody tr')->each(function (Crawler $row) use (&$details, &$playerIndex, $team1Name, $team2Name) {
            try {
                $cells = $row->filter('td');
                if ($cells->count() < 5)
                    return;

                // Determinar equipo basado en posición
                $teamName = ($playerIndex < 5) ? $team1Name : $team2Name;
                $playerIndex++;

                // Nombre del jugador
                $playerCell = $cells->eq(0);
                $playerLink = $playerCell->filter('a');
                $playerName = $playerLink->count() ? trim($playerLink->text()) : trim($playerCell->text());

                // Limpiar nombre (a veces incluye la bandera)
                $playerName = preg_replace('/^\s*\S+\s+/', '', $playerName) ?: $playerName;

                // Intentar obtener stats del texto de las celdas
                $stats = $cells->each(fn($td) => trim($td->text()));

                // Índices típicos: Player, K-D, +/-, ADR, KAST, Rating
                // El orden puede variar, así que buscamos patrones
                $kills = 0;
                $deaths = 0;
                $assists = 0;
                $adr = null;
                $kast = null;
                $rating = null;

                foreach ($stats as $i => $stat) {
                    // K-D pattern (e.g., "25-18")
                    if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $stat, $kdMatch)) {
                        $kills = (int) $kdMatch[1];
                        $deaths = (int) $kdMatch[2];
                    }
                    // ADR (número decimal)
                    if (preg_match('/^[\d.]+$/', $stat) && (float) $stat > 30 && (float) $stat < 200) {
                        $adr = (float) $stat;
                    }
                    // KAST (porcentaje)
                    if (preg_match('/^([\d.]+)%?$/', $stat, $kastMatch) && (float) $kastMatch[1] > 30 && (float) $kastMatch[1] <= 100) {
                        $kast = (float) $kastMatch[1];
                    }
                    // Rating (1.xx format)
                    if (preg_match('/^[01]\.\d{2}$/', $stat)) {
                        $rating = (float) $stat;
                    }
                }

                if (!empty($playerName) && $playerName !== 'Player') {
                    $details['players'][] = [
                        'name' => $playerName,
                        'team' => $teamName,
                        'kills' => $kills,
                        'deaths' => $deaths,
                        'assists' => $assists,
                        'adr' => $adr,
                        'kast' => $kast,
                        'rating' => $rating,
                        'agent' => '', // CS2 no tiene agentes
                        'data_source' => 'hltv'
                    ];
                }
            } catch (Exception $e) {
                // Skip malformed row
            }
        });

        return $details;
    }

    /**
     * Busca un partido en HLTV por nombres de equipo
     * 
     * @param string $team1 Nombre del primer equipo
     * @param string $team2 Nombre del segundo equipo
     * @param string|null $date Fecha opcional para filtrar
     * @return string|null HLTV match ID si se encuentra
     */
    public function findMatchByTeams(string $team1, string $team2, ?string $date = null): ?string
    {
        $team1 = strtolower(trim($team1));
        $team2 = strtolower(trim($team2));

        // Buscar en resultados recientes
        $results = $this->scrapeResults();

        foreach ($results as $match) {
            $matchTeam1 = strtolower(trim($match['team1']));
            $matchTeam2 = strtolower(trim($match['team2']));

            // Comparación flexible
            $t1Match = ($matchTeam1 === $team1 || str_contains($matchTeam1, $team1) || str_contains($team1, $matchTeam1));
            $t2Match = ($matchTeam2 === $team2 || str_contains($matchTeam2, $team2) || str_contains($team2, $matchTeam2));

            // También en orden inverso
            $t1Inv = ($matchTeam1 === $team2 || str_contains($matchTeam1, $team2) || str_contains($team2, $matchTeam1));
            $t2Inv = ($matchTeam2 === $team1 || str_contains($matchTeam2, $team1) || str_contains($team1, $matchTeam2));

            if (($t1Match && $t2Match) || ($t1Inv && $t2Inv)) {
                return $match['hltv_match_id'];
            }
        }

        return null;
    }

    /**
     * Detecta región basándose en el nombre del torneo
     * 
     * @param string $tournament Nombre del torneo
     * @return string Región detectada
     */
    protected function detectRegion(string $tournament): string
    {
        $tournament = strtoupper($tournament);

        // Americas
        if (
            str_contains($tournament, 'AMERICAS') || str_contains($tournament, 'NORTH AMERICA') ||
            str_contains($tournament, 'BRAZIL') || str_contains($tournament, 'LATAM') ||
            str_contains($tournament, 'NA ') || str_contains($tournament, 'ESL PRO LEAGUE') ||
            str_contains($tournament, 'FLASHPOINT')
        ) {
            return 'Americas';
        }

        // Europe
        if (
            str_contains($tournament, 'EUROPE') || str_contains($tournament, 'EU ') ||
            str_contains($tournament, 'CIS') || str_contains($tournament, 'NORDIC') ||
            str_contains($tournament, 'FACEIT') || str_contains($tournament, 'BLAST')
        ) {
            return 'EMEA';
        }

        // Asia/Pacific
        if (
            str_contains($tournament, 'ASIA') || str_contains($tournament, 'APAC') ||
            str_contains($tournament, 'CHINA') || str_contains($tournament, 'OCEANIA') ||
            str_contains($tournament, 'KOREA') || str_contains($tournament, 'JAPAN') ||
            str_contains($tournament, 'SEA')
        ) {
            return 'Pacific';
        }

        // International (Major tournaments)
        if (
            str_contains($tournament, 'MAJOR') || str_contains($tournament, 'IEM') ||
            str_contains($tournament, 'INTEL EXTREME') || str_contains($tournament, 'WORLD') ||
            str_contains($tournament, 'COLOGNE') || str_contains($tournament, 'KATOWICE')
        ) {
            return 'International';
        }

        return 'Other';
    }

    /**
     * Calcula importancia del partido
     * 
     * @param string $tournament Nombre del torneo
     * @param int $stars Estrellas HLTV (0-5)
     * @return int Puntuación de importancia
     */
    protected function calculateImportance(string $tournament, int $stars): int
    {
        $tournament = strtoupper($tournament);
        $score = 20 + ($stars * 15); // Base + stars bonus

        // Major tournaments
        if (str_contains($tournament, 'MAJOR')) {
            $score += 80;
        } elseif (str_contains($tournament, 'IEM') || str_contains($tournament, 'INTEL EXTREME')) {
            $score += 60;
        } elseif (str_contains($tournament, 'BLAST') || str_contains($tournament, 'ESL PRO')) {
            $score += 50;
        } elseif (str_contains($tournament, 'DREAMHACK')) {
            $score += 40;
        }

        // Stage bonuses
        if (str_contains($tournament, 'FINAL') || str_contains($tournament, 'GRAND')) {
            $score += 30;
        } elseif (str_contains($tournament, 'SEMIFINAL') || str_contains($tournament, 'PLAYOFF')) {
            $score += 20;
        } elseif (str_contains($tournament, 'QUARTER')) {
            $score += 10;
        }

        return min($score, 200);
    }
}
