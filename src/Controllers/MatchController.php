<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;
use App\Classes\VlrScraper;
use App\Models\MatchModel;
use App\Models\PlayerStatsModel;
use Exception;

class MatchController
{
    private MatchModel $model;

    public function __construct()
    {
        $this->model = new MatchModel();
    }

    public function index(?string $gameType = null)
    {
        $matches = [];
        $error = $_SESSION['error'] ?? null;
        $message = $_SESSION['message'] ?? null;

        // Pass current filter to view
        $activeTab = $gameType ?? 'all';
        $activeRegion = $_GET['region'] ?? 'all';
        $activeStatus = $_GET['status'] ?? 'all';

        // Limpiar flash messages una vez leídos
        unset($_SESSION['error']);
        unset($_SESSION['message']);

        try {
            $matches = $this->model->getAllMatches($gameType, $activeRegion, $activeStatus);
        } catch (Exception $e) {
            $error = "No se pudo conectar a la base de datos o leer partidos: " . $e->getMessage();
        }

        // Cargar Vista
        require __DIR__ . '/../../views/home.php';
    }

    public function scrape()
    {
        $force = isset($_GET['force']) && $_GET['force'] === '1';

        try {
            // Check if we already have cached data
            if (!$force && $this->model->hasCachedMatches()) {
                $count = $this->model->getMatchCount();
                $_SESSION['message'] = "Mostrando $count partidos de la caché. <a href='?action=scrape&force=1' class='underline'>Forzar actualización</a>";

                // Just redirect without scraping
                $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
                header('Location: ' . $redirectUrl);
                exit;
            }

            // Limpiar datos antiguos antes de scrapear
            $this->model->deleteAllMatches();

            // Instanciar Scrapers
            $scrapers = [
                new ValorantScraper(),
                new LolScraper(),
                new Cs2Scraper()
            ];

            $totalMatches = 0;
            foreach ($scrapers as $scraper) {
                $data = $scraper->scrapeMatches();
                if (!empty($data)) {
                    $this->model->saveMatches($data);
                    $totalMatches += count($data);
                }
            }

            if ($totalMatches > 0) {
                $_SESSION['message'] = "Scraping completado: $totalMatches partidos obtenidos.";
            } else {
                $_SESSION['error'] = "Scraping completado pero no se obtuvieron partidos. Liquipedia puede estar limitando las peticiones. Intenta de nuevo en unos minutos.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error durante el scraping: " . $e->getMessage();
        }

        // Redireccionar a la raíz del proyecto (dinámico)
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
        header('Location: ' . $redirectUrl);
        exit;
    }

    public function reset()
    {
        try {
            $this->model->deleteAllMatches();
            $_SESSION['message'] = "Base de datos limpiada correctamente.";
        } catch (Exception $e) {
            $_SESSION['error'] = "No se pudo limpiar la base de datos: " . $e->getMessage();
        }

        // Redireccionar a la raíz del proyecto (dinámico)
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
        header('Location: ' . $redirectUrl);
        exit;
    }
    public function show()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $match = $this->model->getMatchById((int) $id);

        if (!$match) {
            $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
            header('Location: ' . $redirectUrl);
            exit;
        }

        // On-demand scraping for details
        if (empty($match['match_details']) && !empty($match['match_url'])) {
            try {
                $scraper = match ($match['game_type']) {
                    'valorant' => new ValorantScraper(),
                    'lol' => new LolScraper(),
                    'cs2' => new Cs2Scraper(),
                    default => null
                };

                if ($scraper) {
                    $details = $scraper->scrapeMatchDetails($match['match_url']);
                    if (!empty($details)) {
                        $this->model->updateMatchDetails($match['id'], $details);
                        // Refresh match data
                        $match['match_details'] = json_encode($details);
                        // Also merge into array for view usage if needed
                    }
                }
            } catch (Exception $e) {
                // Log error or ignore, simpler to just show what we have
                error_log("Failed to scrape details: " . $e->getMessage());
            }
        }

        // Enriquecer con VLR.gg stats para Valorant
        $vlrStats = [];
        if ($match['game_type'] === 'valorant' && $match['match_status'] === 'completed') {
            try {
                $playerStatsModel = new PlayerStatsModel($this->model->getConnection());

                // Verificar si ya tenemos stats de VLR
                if (!$playerStatsModel->hasVlrStats($match['id'])) {
                    // Intentar obtener stats de VLR.gg
                    $vlrScraper = new VlrScraper();

                    // Si ya tenemos vlr_match_id, usarlo directamente
                    if (!empty($match['vlr_match_id'])) {
                        $vlrDetails = $vlrScraper->scrapeMatchDetails($match['vlr_match_id']);
                        if (!empty($vlrDetails['players'])) {
                            $playerStatsModel->saveStats($match['id'], $vlrDetails['players']);
                        }
                    } else {
                        // Buscar por nombres de equipo
                        $vlrMatchId = $vlrScraper->findMatchByTeams(
                            $match['team1_name'],
                            $match['team2_name'],
                            $match['match_time']
                        );

                        if ($vlrMatchId) {
                            $this->model->updateVlrMatchId($match['id'], $vlrMatchId);
                            $vlrDetails = $vlrScraper->scrapeMatchDetails($vlrMatchId);
                            if (!empty($vlrDetails['players'])) {
                                $playerStatsModel->saveStats($match['id'], $vlrDetails['players']);
                            }
                        }
                    }
                }

                // Obtener stats guardadas
                $vlrStats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
            } catch (Exception $e) {
                error_log("Failed to enrich with VLR stats: " . $e->getMessage());
            }
        }

        // Decode details for view
        $match['details_decoded'] = !empty($match['match_details']) ? json_decode($match['match_details'], true) : [];
        $match['vlr_stats'] = $vlrStats;

        // Merge stats from both sources (VLR prioritized, Liquipedia as fallback)
        $match['merged_stats'] = $this->mergePlayerStats($match);

        require __DIR__ . '/../../views/match_detail.php';
    }

    /**
     * Merge player stats from VLR.gg and Liquipedia into unified format
     * VLR stats are prioritized (more complete: ACS, ADR, KAST, HS%)
     * Liquipedia stats used as fallback
     */
    private function mergePlayerStats(array $match): array
    {
        $mergedByTeam = [];
        $team1Name = $match['team1_name'];
        $team2Name = $match['team2_name'];

        // Initialize teams
        $mergedByTeam[$team1Name] = [];
        $mergedByTeam[$team2Name] = [];

        // First, add VLR stats (prioritized source - more complete data)
        $vlrStats = $match['vlr_stats'] ?? [];
        $vlrTeam1 = $vlrStats['team1'] ?? [];
        $vlrTeam2 = $vlrStats['team2'] ?? [];

        foreach ($vlrTeam1 as $player) {
            $mergedByTeam[$team1Name][] = [
                'name' => $player['player_name'] ?? $player['name'] ?? 'Unknown',
                'agent' => $player['agent'] ?? null,
                'kills' => $player['kills'] ?? 0,
                'deaths' => $player['deaths'] ?? 0,
                'assists' => $player['assists'] ?? 0,
                'acs' => $player['acs'] ?? null,
                'adr' => $player['adr'] ?? null,
                'kast' => $player['kast'] ?? null,
                'hs_percent' => $player['hs_percent'] ?? null,
                'first_bloods' => $player['first_bloods'] ?? null,
                'data_source' => 'vlr'
            ];
        }

        foreach ($vlrTeam2 as $player) {
            $mergedByTeam[$team2Name][] = [
                'name' => $player['player_name'] ?? $player['name'] ?? 'Unknown',
                'agent' => $player['agent'] ?? null,
                'kills' => $player['kills'] ?? 0,
                'deaths' => $player['deaths'] ?? 0,
                'assists' => $player['assists'] ?? 0,
                'acs' => $player['acs'] ?? null,
                'adr' => $player['adr'] ?? null,
                'kast' => $player['kast'] ?? null,
                'hs_percent' => $player['hs_percent'] ?? null,
                'first_bloods' => $player['first_bloods'] ?? null,
                'data_source' => 'vlr'
            ];
        }

        // If no VLR stats, use Liquipedia stats as fallback
        if (empty($mergedByTeam[$team1Name]) && empty($mergedByTeam[$team2Name])) {
            $liquipediaPlayers = $match['details_decoded']['players'] ?? [];

            foreach ($liquipediaPlayers as $player) {
                $teamName = $player['team'] ?? 'Unknown';
                // Match to team1 or team2
                if (stripos($teamName, $team1Name) !== false || stripos($team1Name, $teamName) !== false) {
                    $targetTeam = $team1Name;
                } elseif (stripos($teamName, $team2Name) !== false || stripos($team2Name, $teamName) !== false) {
                    $targetTeam = $team2Name;
                } else {
                    // Fallback: first 5 players to team1, rest to team2
                    $targetTeam = count($mergedByTeam[$team1Name]) < 5 ? $team1Name : $team2Name;
                }

                $mergedByTeam[$targetTeam][] = [
                    'name' => $player['name'] ?? 'Unknown',
                    'agent' => $player['agent'] ?? null,
                    'kills' => $player['kills'] ?? 0,
                    'deaths' => $player['deaths'] ?? 0,
                    'assists' => $player['assists'] ?? 0,
                    'acs' => null, // Not available from Liquipedia
                    'adr' => null,
                    'kast' => null,
                    'hs_percent' => null,
                    'first_bloods' => null,
                    'data_source' => 'liquipedia'
                ];
            }
        }

        return $mergedByTeam;
    }
}
