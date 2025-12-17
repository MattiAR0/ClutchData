<?php

namespace App\Controllers;

use App\Classes\TeamScraper;
use App\Classes\PlayerScraper;
use App\Models\TeamModel;
use App\Models\PlayerModel;
use Exception;

/**
 * Controller for team-related pages
 */
class TeamController
{
    private TeamModel $teamModel;
    private PlayerModel $playerModel;

    public function __construct()
    {
        $this->teamModel = new TeamModel();
        $this->playerModel = new PlayerModel();
    }

    /**
     * List all teams (from matches or cached in DB)
     */
    public function index(?string $gameType = null): void
    {
        $error = $_SESSION['error'] ?? null;
        $message = $_SESSION['message'] ?? null;
        unset($_SESSION['error'], $_SESSION['message']);

        $activeTab = $gameType ?? 'all';
        $activeRegion = $_GET['region'] ?? 'all';

        try {
            // Get teams from database
            $teams = $this->teamModel->getAllTeams($gameType !== 'all' ? $gameType : null, $activeRegion !== 'all' ? $activeRegion : null);

            // If no teams in DB, get unique teams from matches
            if (empty($teams)) {
                $matchTeams = $this->teamModel->getTeamsFromMatches($gameType !== 'all' ? $gameType : null);
                // Convert to display format
                $teams = array_map(fn($t) => [
                    'id' => null,
                    'name' => $t['name'],
                    'game_type' => $t['game_type'],
                    'region' => 'Unknown',
                    'logo_url' => null
                ], $matchTeams);
            }
        } catch (Exception $e) {
            $error = "Error loading teams: " . $e->getMessage();
            $teams = [];
        }

        require __DIR__ . '/../../views/teams.php';
    }

    /**
     * Show team details with on-demand scraping
     */
    public function show(): void
    {
        $name = $_GET['name'] ?? null;
        $gameType = $_GET['game'] ?? 'valorant';

        if (!$name) {
            $this->redirectToIndex();
            return;
        }

        // Decode URL-encoded name
        $name = urldecode($name);

        // Try to get team from database
        $team = $this->teamModel->getTeamByNameAndGame($name, $gameType);
        $roster = [];
        $matches = [];
        $scraped = false;

        // On-demand scraping if team not in DB or needs refresh
        if (!$team || empty($team['description'])) {
            try {
                $scraper = new TeamScraper();
                $scrapedData = $scraper->scrapeTeam($name, $gameType);

                if ($scrapedData) {
                    $scraped = true;
                    // Save team to database
                    $teamId = $this->teamModel->saveTeam([
                        'name' => $scrapedData['name'],
                        'game_type' => $scrapedData['game_type'],
                        'region' => $scrapedData['region'],
                        'country' => $scrapedData['country'],
                        'logo_url' => $scrapedData['logo_url'],
                        'description' => $scrapedData['description'],
                        'liquipedia_url' => $scrapedData['liquipedia_url']
                    ]);

                    $team = $this->teamModel->getTeamById($teamId);
                    $roster = $scrapedData['roster'] ?? [];

                    // Save roster players to database
                    foreach ($roster as $playerData) {
                        $this->playerModel->savePlayer([
                            'nickname' => $playerData['nickname'],
                            'game_type' => $gameType,
                            'team_id' => $teamId,
                            'role' => $playerData['role'] ?? null,
                            'country' => $playerData['country'] ?? null,
                            'liquipedia_url' => $playerData['liquipedia_url'] ?? null
                        ]);
                    }
                }
            } catch (Exception $e) {
                error_log("Team scraping failed: " . $e->getMessage());
            }
        }

        // Get roster from database if not scraped
        if (!$scraped && $team) {
            $roster = $this->playerModel->getPlayersByTeam($team['id']);
        }

        // Get team's matches
        $matches = $this->teamModel->getTeamMatches($name, $gameType, 10);

        // If still no team data, show basic info
        if (!$team) {
            $team = [
                'id' => null,
                'name' => $name,
                'game_type' => $gameType,
                'region' => 'Unknown',
                'description' => 'No information available for this team.',
                'logo_url' => null,
                'liquipedia_url' => null
            ];
        }

        require __DIR__ . '/../../views/team_detail.php';
    }

    /**
     * Redirect to teams index
     */
    private function redirectToIndex(): void
    {
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/teams';
        header('Location: ' . $redirectUrl);
        exit;
    }
}
