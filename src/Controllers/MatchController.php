<?php

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
        try {
            // Limpiar datos antiguos antes de scrapear
            $this->model->deleteAllMatches();

            // Instanciar Scrapers
            $scrapers = [
                new ValorantScraper(),
                new LolScraper(),
                new Cs2Scraper()
            ];

            foreach ($scrapers as $scraper) {
                $data = $scraper->scrapeMatches();
                $this->model->saveMatches($data);
            }

            $_SESSION['message'] = "Scraping completado exitosamente.";
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

        require __DIR__ . '/../../views/match_detail.php';
    }
}
