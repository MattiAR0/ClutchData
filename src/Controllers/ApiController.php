<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MatchModel;
use App\Models\TeamModel;
use App\Models\PlayerModel;
use App\Models\PlayerStatsModel;
use App\Classes\Logger;
use App\Classes\VlrScraper;
use App\Classes\HltvScraper;
use Exception;

/**
 * Controlador API REST
 * Expone endpoints JSON para acceso programático a los datos
 * 
 * Endpoints disponibles:
 *   GET /api/matches       - Lista todos los partidos
 *   GET /api/matches/{id}  - Detalle de un partido
 *   GET /api/teams         - Lista todos los equipos
 *   GET /api/teams/{name}  - Detalle de un equipo
 *   GET /api/stats         - Estadísticas generales
 * 
 * @package App\Controllers
 */
class ApiController
{
    private MatchModel $matchModel;
    private TeamModel $teamModel;
    private PlayerModel $playerModel;
    private Logger $logger;

    public function __construct()
    {
        $this->matchModel = new MatchModel();
        $this->teamModel = new TeamModel();
        $this->playerModel = new PlayerModel();
        $this->logger = Logger::getInstance();
    }

    /**
     * Envía respuesta JSON con código HTTP apropiado
     */
    private function jsonResponse(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // Log API access
        $this->logger->logApiAccess(
            $_SERVER['REQUEST_URI'] ?? '/api',
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $statusCode
        );

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Envía respuesta de error JSON
     */
    private function errorResponse(string $message, int $statusCode = 400): void
    {
        $this->jsonResponse([
            'success' => false,
            'error' => $message,
            'code' => $statusCode
        ], $statusCode);
    }

    /**
     * GET /api/matches
     * Lista partidos con filtros opcionales
     * 
     * Query params:
     *   - game: valorant|lol|cs2
     *   - region: Americas|EMEA|Pacific|Other
     *   - status: upcoming|completed|all
     *   - limit: número máximo de resultados
     */
    public function getMatches(): void
    {
        try {
            $game = $_GET['game'] ?? null;
            $region = $_GET['region'] ?? null;
            $status = $_GET['status'] ?? null;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

            $matches = $this->matchModel->getAllMatches($game, $region, $status);

            if ($limit && $limit > 0) {
                $matches = array_slice($matches, 0, $limit);
            }

            $this->jsonResponse([
                'success' => true,
                'count' => count($matches),
                'filters' => [
                    'game' => $game,
                    'region' => $region,
                    'status' => $status
                ],
                'data' => $matches
            ]);
        } catch (Exception $e) {
            $this->logger->error("API getMatches error", ['error' => $e->getMessage()]);
            $this->errorResponse('Error retrieving matches: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/matches/{id}
     * Obtiene detalle de un partido específico
     */
    public function getMatch(): void
    {
        try {
            $id = $_GET['id'] ?? null;

            if (!$id || !is_numeric($id)) {
                $this->errorResponse('Match ID is required and must be numeric', 400);
            }

            $match = $this->matchModel->getMatchById((int) $id);

            if (!$match) {
                $this->errorResponse('Match not found', 404);
            }

            // Decode match details if present
            if (!empty($match['match_details'])) {
                $match['details'] = json_decode($match['match_details'], true);
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $match
            ]);
        } catch (Exception $e) {
            $this->logger->error("API getMatch error", ['error' => $e->getMessage()]);
            $this->errorResponse('Error retrieving match: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/teams
     * Lista equipos con filtros opcionales
     * 
     * Query params:
     *   - game: valorant|lol|cs2
     *   - region: Americas|EMEA|Pacific|Other
     */
    public function getTeams(): void
    {
        try {
            $game = $_GET['game'] ?? null;
            $region = $_GET['region'] ?? null;

            $teams = $this->teamModel->getAllTeams($game, $region);

            // If no teams in DB, get from matches
            if (empty($teams)) {
                $matchTeams = $this->teamModel->getTeamsFromMatches($game);
                $teams = array_map(fn($t) => [
                    'name' => $t['name'],
                    'game_type' => $t['game_type'],
                    'region' => 'Unknown',
                    'source' => 'matches'
                ], $matchTeams);
            }

            $this->jsonResponse([
                'success' => true,
                'count' => count($teams),
                'filters' => [
                    'game' => $game,
                    'region' => $region
                ],
                'data' => $teams
            ]);
        } catch (Exception $e) {
            $this->logger->error("API getTeams error", ['error' => $e->getMessage()]);
            $this->errorResponse('Error retrieving teams: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/teams/{name}
     * Obtiene detalle de un equipo específico
     */
    public function getTeam(): void
    {
        try {
            $name = $_GET['name'] ?? null;
            $game = $_GET['game'] ?? 'valorant';

            if (!$name) {
                $this->errorResponse('Team name is required', 400);
            }

            $team = $this->teamModel->getTeamByNameAndGame(urldecode($name), $game);

            if (!$team) {
                $this->errorResponse('Team not found', 404);
            }

            // Get team roster
            $roster = $this->playerModel->getPlayersByTeam($team['id']);
            $team['roster'] = $roster;

            // Get team matches
            $matches = $this->teamModel->getTeamMatches($team['name'], $game, 5);
            $team['recent_matches'] = $matches;

            $this->jsonResponse([
                'success' => true,
                'data' => $team
            ]);
        } catch (Exception $e) {
            $this->logger->error("API getTeam error", ['error' => $e->getMessage()]);
            $this->errorResponse('Error retrieving team: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/stats
     * Estadísticas generales de la plataforma
     */
    public function getStats(): void
    {
        try {
            $allMatches = $this->matchModel->getAllMatches(null, null, 'all');
            $teams = $this->teamModel->getAllTeams();

            $valorantMatches = array_filter($allMatches, fn($m) => $m['game_type'] === 'valorant');
            $lolMatches = array_filter($allMatches, fn($m) => $m['game_type'] === 'lol');
            $cs2Matches = array_filter($allMatches, fn($m) => $m['game_type'] === 'cs2');

            $upcomingMatches = array_filter($allMatches, fn($m) => $m['match_status'] === 'upcoming');
            $completedMatches = array_filter($allMatches, fn($m) => $m['match_status'] === 'completed');

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'total_matches' => count($allMatches),
                    'upcoming_matches' => count($upcomingMatches),
                    'completed_matches' => count($completedMatches),
                    'total_teams' => count($teams),
                    'matches_by_game' => [
                        'valorant' => count($valorantMatches),
                        'lol' => count($lolMatches),
                        'cs2' => count($cs2Matches)
                    ],
                    'last_updated' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error("API getStats error", ['error' => $e->getMessage()]);
            $this->errorResponse('Error retrieving stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Maneja OPTIONS request para CORS preflight
     */
    public function options(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(204);
        exit;
    }

    /**
     * GET /api/match/stats
     * Obtiene estadísticas avanzadas de un partido (VLR/HLTV) de forma asíncrona.
     * Este endpoint realiza scraping solo si las stats no están cacheadas.
     * 
     * Query params:
     *   - id: Match ID (requerido)
     * 
     * Response:
     *   - cached: boolean indicando si los datos estaban en caché
     *   - stats: array de estadísticas de jugadores agrupadas por equipo
     */
    public function getMatchStats(): void
    {
        try {
            $id = $_GET['id'] ?? null;

            if (!$id || !is_numeric($id)) {
                $this->errorResponse('Match ID is required and must be numeric', 400);
            }

            $match = $this->matchModel->getMatchById((int) $id);

            if (!$match) {
                $this->errorResponse('Match not found', 404);
            }

            $playerStatsModel = new PlayerStatsModel($this->matchModel->getConnection());
            $stats = [];
            $cached = false;
            $source = null;

            // Valorant: primero cache, luego Liquipedia (rápido), luego VLR (enriquecido)
            if ($match['game_type'] === 'valorant' && $match['match_status'] === 'completed') {
                // 1. Verificar si ya tenemos stats cacheadas de VLR (prioritaria)
                if ($playerStatsModel->hasVlrStats($match['id'])) {
                    $stats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                    $cached = true;
                    $source = 'vlr';
                }
                // 2. Verificar si tenemos stats de Liquipedia
                elseif ($playerStatsModel->hasStats($match['id'])) {
                    $stats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                    $cached = true;
                    $source = 'liquipedia';
                    // Indicar que se puede enriquecer con VLR más tarde
                    $canEnrich = true;
                }
                // 3. Si no hay cache, intentar Liquipedia primero (más rápido)
                else {
                    // Primero intentar obtener de match_details guardados
                    if (!empty($match['match_details'])) {
                        $details = json_decode($match['match_details'], true);
                        if (!empty($details['players'])) {
                            foreach ($details['players'] as $player) {
                                $player['data_source'] = 'liquipedia';
                            }
                            $playerStatsModel->saveStats($match['id'], $details['players'], 'overall');

                            // Guardar stats por mapa si existen
                            if (!empty($details['players_by_map'])) {
                                foreach ($details['players_by_map'] as $mapName => $mapPlayers) {
                                    $playerStatsModel->saveStats($match['id'], $mapPlayers, $mapName);
                                }
                            }

                            $stats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                            $source = 'liquipedia';
                            $canEnrich = true;
                        }
                    }

                    // 4. Si aún no hay stats, intentar VLR (puede ser más lento)
                    if (empty($stats)) {
                        try {
                            $vlrScraper = new VlrScraper();
                            $vlrMatchId = $match['vlr_match_id'];

                            if (empty($vlrMatchId)) {
                                $vlrMatchId = $vlrScraper->findMatchByTeams(
                                    $match['team1_name'],
                                    $match['team2_name'],
                                    $match['match_time']
                                );
                                if ($vlrMatchId) {
                                    $this->matchModel->updateVlrMatchId($match['id'], $vlrMatchId);
                                }
                            }

                            if ($vlrMatchId) {
                                $vlrDetails = $vlrScraper->scrapeMatchDetails($vlrMatchId);

                                if (!empty($vlrDetails['players'])) {
                                    $playerStatsModel->saveStats($match['id'], $vlrDetails['players'], 'overall');
                                }

                                if (!empty($vlrDetails['players_by_map'])) {
                                    foreach ($vlrDetails['players_by_map'] as $mapName => $mapPlayers) {
                                        $playerStatsModel->saveStats($match['id'], $mapPlayers, $mapName);
                                    }
                                }

                                $stats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                                $source = 'vlr';
                            }
                        } catch (Exception $e) {
                            $this->logger->warning("VLR scraping failed", ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            // CS2: usar HLTV
            if ($match['game_type'] === 'cs2' && $match['match_status'] === 'completed') {
                // Verificar si ya tenemos stats cacheadas
                if ($playerStatsModel->hasHltvStats($match['id'])) {
                    $stats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                    $cached = true;
                    $source = 'hltv';
                } else {
                    // Scraping asíncrono
                    try {
                        $hltvScraper = new HltvScraper();

                        // Si ya tenemos hltv_match_id, usarlo directamente
                        if (!empty($match['hltv_match_id'])) {
                            $hltvDetails = $hltvScraper->scrapeMatchDetails($match['hltv_match_id']);
                            if (!empty($hltvDetails['players'])) {
                                $playerStatsModel->saveStats($match['id'], $hltvDetails['players']);
                                $stats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                                $source = 'hltv';
                            }
                        } else {
                            // Buscar por nombres de equipo
                            $hltvMatchId = $hltvScraper->findMatchByTeams(
                                $match['team1_name'],
                                $match['team2_name'],
                                $match['match_time']
                            );

                            if ($hltvMatchId) {
                                $this->matchModel->updateHltvMatchId($match['id'], $hltvMatchId);
                                $hltvDetails = $hltvScraper->scrapeMatchDetails($hltvMatchId);
                                if (!empty($hltvDetails['players'])) {
                                    $playerStatsModel->saveStats($match['id'], $hltvDetails['players']);
                                    $stats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                                    $source = 'hltv';
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->logger->warning("HLTV scraping failed", ['error' => $e->getMessage()]);
                    }
                }
            }

            // LoL y otros: devolver stats de Liquipedia si existen
            if (empty($stats) && !empty($match['match_details'])) {
                $details = json_decode($match['match_details'], true);
                if (!empty($details['players'])) {
                    $stats = $details['players'];
                    $source = 'liquipedia';
                    $cached = true;
                }
            }

            // Obtener mapas disponibles y filtrar si se especifica
            $mapFilter = $_GET['map'] ?? null;
            $availableMaps = $playerStatsModel->getAvailableMaps($match['id']);

            // Si se solicita un mapa específico, obtener stats de ese mapa
            if ($mapFilter && in_array($mapFilter, $availableMaps)) {
                $stats = $playerStatsModel->getStatsByMatchGroupedWithMap($match['id'], $mapFilter);
            }

            $this->jsonResponse([
                'success' => true,
                'cached' => $cached,
                'source' => $source,
                'match_id' => (int) $id,
                'game_type' => $match['game_type'],
                'available_maps' => $availableMaps,
                'current_map' => $mapFilter ?? 'overall',
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            $this->logger->error("API getMatchStats error", ['error' => $e->getMessage()]);
            $this->errorResponse('Error retrieving match stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/auto-update
     * Triggers background update of matches and stats.
     * Rate limited: only runs once every 5 minutes.
     * Called automatically when user visits the home page.
     */
    public function autoUpdate(): void
    {
        try {
            $lockFile = __DIR__ . '/../../logs/last_update.lock';
            $updateInterval = 300; // 5 minutes in seconds

            // Check if we should update
            $lastUpdate = file_exists($lockFile) ? (int) file_get_contents($lockFile) : 0;
            $now = time();

            if (($now - $lastUpdate) < $updateInterval) {
                $this->jsonResponse([
                    'success' => true,
                    'updated' => false,
                    'message' => 'Update skipped (last update was ' . ($now - $lastUpdate) . 's ago)',
                    'next_update_in' => $updateInterval - ($now - $lastUpdate)
                ]);
                return;
            }

            // Update lock file immediately
            @file_put_contents($lockFile, (string) $now);

            // Perform lightweight update (only new matches, max 3)
            $scrapers = [
                'valorant' => new \App\Classes\ValorantScraper(),
                'cs2' => new \App\Classes\Cs2Scraper(),
                'lol' => new \App\Classes\LolScraper()
            ];

            $results = [];
            foreach ($scrapers as $game => $scraper) {
                try {
                    $matches = $scraper->scrapeMatches();
                    $saved = 0;
                    foreach ($matches as $match) {
                        if ($this->matchModel->saveMatch($match)) {
                            $saved++;
                        }
                    }
                    $results[$game] = ['found' => count($matches), 'new' => $saved];
                } catch (Exception $e) {
                    $results[$game] = ['error' => $e->getMessage()];
                }
            }

            // Also update 2 completed matches without per-map data
            $statsModel = new PlayerStatsModel($this->matchModel->getConnection());
            $db = $this->matchModel->getConnection();

            $sql = "SELECT DISTINCT m.id, m.game_type, m.match_url
                    FROM matches m
                    LEFT JOIN player_stats ps ON m.id = ps.match_id AND ps.map_name != 'overall'
                    WHERE m.match_status = 'completed'
                    AND m.match_url IS NOT NULL
                    AND ps.id IS NULL
                    ORDER BY m.match_time DESC
                    LIMIT 2";

            $stmt = $db->query($sql);
            $matchesToUpdate = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $updatedMaps = 0;
            foreach ($matchesToUpdate as $match) {
                $scraper = $scrapers[$match['game_type']] ?? null;
                if (!$scraper)
                    continue;

                try {
                    $details = $scraper->scrapeMatchDetails($match['match_url']);
                    if (!empty($details['players_by_map'])) {
                        foreach ($details['players_by_map'] as $mapName => $players) {
                            foreach ($players as &$player) {
                                $player['data_source'] = 'liquipedia';
                            }
                            $statsModel->saveStats($match['id'], $players, $mapName);
                        }
                        $updatedMaps++;
                    }
                } catch (Exception $e) {
                    // Skip failed matches
                }
            }

            $this->jsonResponse([
                'success' => true,
                'updated' => true,
                'timestamp' => $now,
                'results' => $results,
                'matches_with_maps_updated' => $updatedMaps
            ]);

        } catch (Exception $e) {
            $this->logger->error("Auto-update error", ['error' => $e->getMessage()]);
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
