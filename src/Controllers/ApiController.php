<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MatchModel;
use App\Models\TeamModel;
use App\Models\PlayerModel;
use App\Classes\Logger;
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
}
