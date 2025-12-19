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
}
