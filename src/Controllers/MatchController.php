<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;
use App\Classes\VlrScraper;
use App\Classes\HltvScraper;
use App\Classes\MatchPredictor;
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
        $syncResult = null;

        // Pass current filter to view
        $activeTab = $gameType ?? 'all';
        $activeRegion = $_GET['region'] ?? 'all';
        $activeStatus = $_GET['status'] ?? 'all';

        // Limpiar flash messages una vez leídos
        unset($_SESSION['error']);
        unset($_SESSION['message']);

        // Sincronizar datos al cargar la página (con rate limiting)
        $syncResult = $this->autoSync();

        try {
            $matches = $this->model->getAllMatches($gameType, $activeRegion, $activeStatus);
        } catch (Exception $e) {
            $error = "No se pudo conectar a la base de datos o leer partidos: " . $e->getMessage();
        }

        // Cargar Vista
        require __DIR__ . '/../../views/home.php';
    }

    /**
     * Sincroniza los partidos automáticamente al cargar la página.
     * Tiene rate limiting de 5 minutos para evitar sobrecargar los scrapers.
     */
    private function autoSync(): array
    {
        $lockFile = __DIR__ . '/../../logs/last_update.lock';
        $updateInterval = 300; // 5 minutos en segundos

        // Verificar si debemos actualizar
        $lastUpdate = file_exists($lockFile) ? (int) file_get_contents($lockFile) : 0;
        $now = time();

        if (($now - $lastUpdate) < $updateInterval) {
            return [
                'updated' => false,
                'message' => 'Datos recientes (última actualización hace ' . ($now - $lastUpdate) . 's)'
            ];
        }

        // Actualizar archivo de lock inmediatamente
        @file_put_contents($lockFile, (string) $now);

        // Actualizar partidos de todos los juegos
        $scrapers = [
            'valorant' => new ValorantScraper(),
            'cs2' => new Cs2Scraper(),
            'lol' => new LolScraper()
        ];

        $results = [];
        $totalNew = 0;

        foreach ($scrapers as $game => $scraper) {
            try {
                $matches = $scraper->scrapeMatches();
                $saved = 0;
                foreach ($matches as $match) {
                    if ($this->model->saveMatch($match)) {
                        $saved++;
                    }
                }
                $results[$game] = ['found' => count($matches), 'new' => $saved];
                $totalNew += $saved;
            } catch (Exception $e) {
                $results[$game] = ['error' => $e->getMessage()];
            }
        }

        return [
            'updated' => true,
            'total_new' => $totalNew,
            'results' => $results
        ];
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

        // On-demand scraping for Liquipedia details (fast - usually cached)
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
                        $match['match_details'] = json_encode($details);
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to scrape details: " . $e->getMessage());
            }
        }

        // Check if we have cached VLR/HLTV stats (NO BLOCKING SCRAPING)
        $vlrStats = [];
        $hltvStats = [];
        $needsAsyncStats = false;

        if ($match['match_status'] === 'completed') {
            try {
                $playerStatsModel = new PlayerStatsModel($this->model->getConnection());

                if ($match['game_type'] === 'valorant') {
                    // Only check cache, don't scrape
                    if ($playerStatsModel->hasVlrStats($match['id'])) {
                        $vlrStats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                    } else {
                        // Mark for async loading via AJAX
                        $needsAsyncStats = true;
                    }
                } elseif ($match['game_type'] === 'cs2') {
                    // Only check cache, don't scrape
                    if ($playerStatsModel->hasHltvStats($match['id'])) {
                        $hltvStats = $playerStatsModel->getStatsByMatchGrouped($match['id']);
                    } else {
                        // Mark for async loading via AJAX
                        $needsAsyncStats = true;
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to check cached stats: " . $e->getMessage());
            }
        }

        // Decode details for view
        $match['details_decoded'] = !empty($match['match_details']) ? json_decode($match['match_details'], true) : [];
        $match['vlr_stats'] = $vlrStats;
        $match['hltv_stats'] = $hltvStats;
        $match['needs_async_stats'] = $needsAsyncStats;

        // Merge stats from both sources (VLR/HLTV prioritized, Liquipedia as fallback)
        $match['merged_stats'] = $this->mergePlayerStats($match);

        // Get AI prediction with Gemini (only for upcoming matches or if no cached explanation)
        if (empty($match['ai_explanation']) && $match['match_status'] === 'upcoming') {
            try {
                $predictor = new MatchPredictor();
                $aiResult = $predictor->predictMatchWithAI($match);

                // Update match data with AI results
                $match['ai_prediction'] = $aiResult['prediction'];
                $match['ai_explanation'] = $aiResult['explanation'];
                $match['ai_source'] = $aiResult['source'];

                // Cache the AI explanation in database
                if (!empty($aiResult['explanation']) && $aiResult['source'] === 'gemini') {
                    $this->saveAiExplanation((int) $match['id'], $aiResult);
                }
            } catch (Exception $e) {
                error_log("AI prediction failed: " . $e->getMessage());
            }
        }

        require __DIR__ . '/../../views/match_detail.php';
    }

    /**
     * Save AI explanation to database cache
     */
    private function saveAiExplanation(int $matchId, array $aiResult): void
    {
        try {
            $db = $this->model->getConnection();
            $stmt = $db->prepare("
                UPDATE matches 
                SET ai_prediction = :prediction, 
                    ai_explanation = :explanation,
                    ai_source = :source 
                WHERE id = :id
            ");
            $stmt->execute([
                ':prediction' => $aiResult['prediction'],
                ':explanation' => $aiResult['explanation'],
                ':source' => $aiResult['source'],
                ':id' => $matchId
            ]);
        } catch (Exception $e) {
            error_log("Failed to save AI explanation: " . $e->getMessage());
        }
    }
    /**
     * Merge player stats from VLR.gg/HLTV and Liquipedia into unified format
     * VLR/HLTV stats are prioritized (more complete: ACS, ADR, KAST, HS%, Rating)
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

        // Determine which stats source to use based on game type
        $stats = [];
        $dataSource = 'liquipedia';

        if ($match['game_type'] === 'valorant') {
            $stats = $match['vlr_stats'] ?? [];
            $dataSource = 'vlr';
        } elseif ($match['game_type'] === 'cs2') {
            $stats = $match['hltv_stats'] ?? [];
            $dataSource = 'hltv';
        }

        $statsTeam1 = $stats['team1'] ?? [];
        $statsTeam2 = $stats['team2'] ?? [];

        // Process team1 stats
        foreach ($statsTeam1 as $player) {
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
                'rating' => $player['rating'] ?? null,
                'first_bloods' => $player['first_bloods'] ?? null,
                'data_source' => $dataSource
            ];
        }

        // Process team2 stats
        foreach ($statsTeam2 as $player) {
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
                'rating' => $player['rating'] ?? null,
                'first_bloods' => $player['first_bloods'] ?? null,
                'data_source' => $dataSource
            ];
        }

        // If no VLR/HLTV stats, use Liquipedia stats as fallback
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
                    'rating' => null,
                    'first_bloods' => null,
                    'data_source' => 'liquipedia'
                ];
            }
        }

        return $mergedByTeam;
    }
}

