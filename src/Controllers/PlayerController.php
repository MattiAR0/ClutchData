<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Classes\PlayerScraper;
use App\Models\PlayerModel;
use App\Models\TeamModel;
use Exception;

/**
 * Controller for player-related pages
 */
class PlayerController
{
    private PlayerModel $playerModel;
    private TeamModel $teamModel;

    public function __construct()
    {
        $this->playerModel = new PlayerModel();
        $this->teamModel = new TeamModel();
    }

    /**
     * List all players with optional game type filter
     */
    public function index(?string $gameType = null): void
    {
        $error = $_SESSION['error'] ?? null;
        $message = $_SESSION['message'] ?? null;
        unset($_SESSION['error'], $_SESSION['message']);

        $activeTab = $gameType ?? $_GET['game'] ?? 'all';
        $activeRegion = $_GET['region'] ?? 'all';

        try {
            $players = $this->playerModel->getAllPlayers(
                $activeTab !== 'all' ? $activeTab : null
            );
        } catch (Exception $e) {
            $error = "Error loading players: " . $e->getMessage();
            $players = [];
        }

        require __DIR__ . '/../../views/players.php';
    }

    /**
     * Show player details with on-demand scraping
     */
    public function show(): void
    {
        $name = $_GET['name'] ?? null;
        $gameType = $_GET['game'] ?? 'valorant';

        if (!$name) {
            $this->redirectToHome();
            return;
        }

        // Decode URL-encoded name
        $name = urldecode($name);

        // Try to get player from database
        $player = $this->playerModel->getPlayerByNickname($name, $gameType);
        $matchStats = [];
        $achievements = [];
        $teamHistory = [];

        // On-demand scraping if player not in DB or needs details
        if (!$player || empty($player['description'])) {
            try {
                $scraper = new PlayerScraper();
                $scrapedData = $scraper->scrapePlayer($name, $gameType);

                if ($scrapedData) {
                    // Find team ID if current_team is set
                    $teamId = null;
                    if (!empty($scrapedData['current_team'])) {
                        $team = $this->teamModel->getTeamByNameAndGame($scrapedData['current_team'], $gameType);
                        $teamId = $team['id'] ?? null;
                    }

                    // Save player to database
                    $playerId = $this->playerModel->savePlayer([
                        'nickname' => $scrapedData['nickname'],
                        'real_name' => $scrapedData['real_name'],
                        'team_id' => $teamId,
                        'game_type' => $scrapedData['game_type'],
                        'country' => $scrapedData['country'],
                        'role' => $scrapedData['role'],
                        'photo_url' => $scrapedData['photo_url'],
                        'birthdate' => $scrapedData['birthdate'],
                        'description' => $scrapedData['description'],
                        'liquipedia_url' => $scrapedData['liquipedia_url']
                    ]);

                    $player = $this->playerModel->getPlayerById($playerId);
                    $achievements = $scrapedData['achievements'] ?? [];
                    $teamHistory = $scrapedData['team_history'] ?? [];
                }
            } catch (Exception $e) {
                error_log("Player scraping failed: " . $e->getMessage());
            }
        }

        // Get player's match stats from database
        $matchStats = $this->playerModel->getPlayerMatchStats($name);

        // If still no player data, show basic info
        if (!$player) {
            $player = [
                'id' => null,
                'nickname' => $name,
                'real_name' => null,
                'game_type' => $gameType,
                'country' => null,
                'role' => null,
                'description' => 'No information available for this player.',
                'photo_url' => null,
                'team_name' => null,
                'liquipedia_url' => null
            ];
        }

        require __DIR__ . '/../../views/player_detail.php';
    }

    /**
     * Redirect to home
     */
    private function redirectToHome(): void
    {
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/';
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Redirect to players index
     */
    private function redirectToIndex(?string $gameFilter = null): void
    {
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/players';
        if ($gameFilter) {
            $redirectUrl .= '?game=' . $gameFilter;
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Sync players from match statistics (player_stats table)
     */
    public function sync(): void
    {
        $gameFilter = $_GET['game'] ?? null;
        $db = $this->playerModel->getConnection();

        $synced = 0;
        $skipped = 0;

        try {
            // Get unique players from player_stats
            $sql = "
                SELECT DISTINCT 
                    ps.player_name as nickname,
                    m.game_type,
                    ps.team_name
                FROM player_stats ps
                JOIN matches m ON ps.match_id = m.id
                WHERE ps.player_name IS NOT NULL 
                  AND ps.player_name != ''
                  AND ps.player_name != 'TBD'
            ";

            $params = [];
            if ($gameFilter && $gameFilter !== 'all') {
                $sql .= " AND m.game_type = :game_type";
                $params['game_type'] = $gameFilter;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $statsPlayers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($statsPlayers as $player) {
                // Check if player already exists
                $existing = $this->playerModel->getPlayerByNickname($player['nickname'], $player['game_type']);

                if (!$existing) {
                    // Find team ID if available
                    $teamId = null;
                    if (!empty($player['team_name'])) {
                        $team = $this->teamModel->getTeamByNameAndGame($player['team_name'], $player['game_type']);
                        $teamId = $team['id'] ?? null;
                    }

                    $this->playerModel->savePlayer([
                        'nickname' => $player['nickname'],
                        'game_type' => $player['game_type'],
                        'team_id' => $teamId
                    ]);
                    $synced++;
                } else {
                    $skipped++;
                }
            }

            $_SESSION['message'] = "✅ Synced $synced players from match stats (skipped $skipped existing)";
        } catch (\Exception $e) {
            $_SESSION['error'] = "Sync error: " . $e->getMessage();
        }

        $this->redirectToIndex($gameFilter !== 'all' ? $gameFilter : null);
    }

    /**
     * Sync players from all teams (scrape rosters)
     * Processes in batches of 5 teams to avoid timeout
     */
    public function syncFromTeams(): void
    {
        $gameFilter = $_GET['game'] ?? null;
        $batchSize = 5; // Process 5 teams per request to avoid timeout
        $offset = (int) ($_GET['offset'] ?? 0);
        $force = isset($_GET['force']);

        $synced = 0;
        $teamsProcessed = 0;
        $teamsSkipped = 0;
        $errors = [];

        try {
            $teamScraper = new \App\Classes\TeamScraper();

            // Get all teams
            $allTeams = $this->teamModel->getAllTeams(
                $gameFilter !== 'all' ? $gameFilter : null
            );

            $totalTeams = count($allTeams);

            // Get batch of teams starting from offset
            $teams = array_slice($allTeams, $offset, $batchSize);

            foreach ($teams as $team) {
                // Skip if team already has a complete roster (5+ players)
                $existingPlayers = $this->playerModel->getPlayersByTeam($team['id']);
                if (!$force && count($existingPlayers) >= 5) {
                    $teamsSkipped++;
                    continue; // Roster seems complete
                }

                $teamsProcessed++;

                try {
                    // Scrape team to get roster
                    $scrapedData = $teamScraper->scrapeTeam($team['name'], $team['game_type']);

                    if ($scrapedData && !empty($scrapedData['roster'])) {
                        foreach ($scrapedData['roster'] as $playerData) {
                            $this->playerModel->savePlayer([
                                'nickname' => $playerData['nickname'],
                                'game_type' => $team['game_type'],
                                'team_id' => $team['id'],
                                'role' => $playerData['role'] ?? null,
                                'country' => $playerData['country'] ?? null,
                                'liquipedia_url' => $playerData['liquipedia_url'] ?? null
                            ]);
                            $synced++;
                        }
                    }

                    // Rate limiting
                    sleep(2);
                } catch (\Exception $e) {
                    $errors[] = $team['name'] . ": " . substr($e->getMessage(), 0, 30);
                }
            }

            // Calculate progress
            $newOffset = $offset + $batchSize;
            $hasMore = $newOffset < $totalTeams;
            $progress = min(100, round(($newOffset / $totalTeams) * 100));

            // Build continue URL
            $continueParams = ['offset' => $newOffset];
            if ($gameFilter && $gameFilter !== 'all') {
                $continueParams['game'] = $gameFilter;
            }
            if ($force) {
                $continueParams['force'] = 1;
            }
            $continueUrl = 'players/sync-from-teams?' . http_build_query($continueParams);

            // Build message
            $message = "✅ Batch complete! Synced $synced players from $teamsProcessed teams";
            if ($teamsSkipped > 0) {
                $message .= " (skipped $teamsSkipped with complete rosters)";
            }
            $message .= " | Progress: $progress% ({$newOffset}/{$totalTeams})";

            if ($hasMore) {
                $message .= " | <a href='{$continueUrl}' class='underline text-emerald-300 hover:text-emerald-100'>⏩ Continue syncing next batch</a>";
            } else {
                $message .= " | ✨ All teams processed!";
            }

            $_SESSION['message'] = $message;

            if (!empty($errors)) {
                $_SESSION['error'] = "Errors: " . implode(', ', array_slice($errors, 0, 3));
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = "Sync error: " . $e->getMessage();
        }

        $this->redirectToIndex($gameFilter !== 'all' ? $gameFilter : null);
    }
}
