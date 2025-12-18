<?php

declare(strict_types=1);

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

            // If no teams in DB (completely empty table), get unique teams from matches
            // We verify hasAnyTeams() to avoid fallback when a FILTER returns empty results but DB has data
            if (empty($teams) && !$this->teamModel->hasAnyTeams()) {
                $matchTeams = $this->teamModel->getTeamsFromMatches($gameType !== 'all' ? $gameType : null);
                // Convert to display format (now includes region from match data)
                $teams = array_map(fn($t) => [
                    'id' => null,
                    'name' => $t['name'],
                    'game_type' => $t['game_type'],
                    'region' => $t['region'] ?? 'Other',
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

    /**
     * Sync teams from matches - bulk scrape team data
     */
    public function sync(): void
    {
        $gameFilter = $_GET['game'] ?? null;
        $limit = (int) ($_GET['limit'] ?? 20); // Default to 20 teams per sync

        $scraper = new TeamScraper();
        $synced = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        try {
            // Get teams from matches that aren't fully scraped yet
            $matchTeams = $this->teamModel->getTeamsFromMatches($gameFilter);

            foreach ($matchTeams as $teamData) {
                if ($synced >= $limit) {
                    break; // Limit reached
                }

                $teamName = $teamData['name'];
                $gameType = $teamData['game_type'];

                // Skip TBD and placeholder names
                if (in_array(strtoupper($teamName), ['TBD', 'TBA', 'UNKNOWN', '???'])) {
                    continue;
                }

                // Check if team already fully scraped (has logo and description)
                $existing = $this->teamModel->getTeamByNameAndGame($teamName, $gameType);
                if ($existing && !empty($existing['logo_url']) && !empty($existing['description'])) {
                    $skipped++;
                    continue;
                }

                try {
                    // Scrape team data
                    $scrapedData = $scraper->scrapeTeam($teamName, $gameType);

                    if ($scrapedData) {
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

                        // Save roster players
                        foreach ($scrapedData['roster'] ?? [] as $playerData) {
                            $this->playerModel->savePlayer([
                                'nickname' => $playerData['nickname'],
                                'game_type' => $gameType,
                                'team_id' => $teamId,
                                'role' => $playerData['role'] ?? null,
                                'country' => $playerData['country'] ?? null,
                                'liquipedia_url' => $playerData['liquipedia_url'] ?? null
                            ]);
                        }

                        $synced++;
                    } else {
                        $failed++;
                        $errors[] = "$teamName ($gameType)";
                    }

                    // Rate limiting - 2 seconds between requests
                    sleep(2);
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "$teamName: " . $e->getMessage();
                }
            }

            // Set session message
            $message = "✅ Synced $synced teams";
            if ($skipped > 0) {
                $message .= " (skipped $skipped already synced)";
            }
            if ($failed > 0) {
                $message .= " | ❌ Failed: $failed";
            }
            $_SESSION['message'] = $message;

            if (!empty($errors) && count($errors) <= 5) {
                $_SESSION['error'] = "Failed teams: " . implode(', ', $errors);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Sync error: " . $e->getMessage();
        }

        // Redirect back to teams page
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/teams';
        if ($gameFilter) {
            $redirectUrl .= '?game=' . $gameFilter;
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Sync regions for all teams that have "Other" region
     * Uses match data to infer regions (excludes international tournaments)
     */
    public function syncRegions(): void
    {
        $gameFilter = $_GET['game'] ?? null;
        $db = $this->teamModel->getConnection();

        // Regiones válidas (no internacionales)
        $validRegions = ['Americas', 'EMEA', 'Pacific'];
        $internationalRegions = ['International', 'World', 'Global', 'Worldwide'];

        $updated = 0;
        $created = 0;
        $skipped = 0;

        try {
            // Paso 1: Obtener la región más frecuente para cada equipo+juego
            // basándose en partidos regionales (excluyendo internacionales)
            $gameCondition = '';
            $params = [];
            if ($gameFilter && $gameFilter !== 'all') {
                $gameCondition = 'AND game_type = :game_type';
                $params['game_type'] = $gameFilter;
            }

            $sql = "
                SELECT 
                    team_name,
                    game_type,
                    match_region,
                    COUNT(*) as match_count
                FROM (
                    SELECT team1_name as team_name, game_type, match_region
                    FROM matches 
                    WHERE match_region IS NOT NULL 
                      AND match_region != 'Other'
                      AND match_region NOT IN ('International', 'World', 'Global', 'Worldwide')
                      AND match_region IN ('Americas', 'EMEA', 'Pacific')
                      $gameCondition
                    
                    UNION ALL
                    
                    SELECT team2_name as team_name, game_type, match_region
                    FROM matches 
                    WHERE match_region IS NOT NULL 
                      AND match_region != 'Other'
                      AND match_region NOT IN ('International', 'World', 'Global', 'Worldwide')
                      AND match_region IN ('Americas', 'EMEA', 'Pacific')
                      $gameCondition
                ) as all_team_matches
                WHERE team_name IS NOT NULL 
                  AND team_name != '' 
                  AND team_name != 'TBD' 
                  AND team_name != 'TBA'
                GROUP BY team_name, game_type, match_region
                ORDER BY team_name, game_type, match_count DESC
            ";

            $stmt = $db->prepare($sql);

            // Bind parameters for both UNION parts
            if ($gameFilter && $gameFilter !== 'all') {
                $stmt->execute(['game_type' => $gameFilter]);
            } else {
                $stmt->execute();
            }

            $regionData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Paso 2: Agrupar por equipo+juego (tomar la región más frecuente)
            $teamRegions = [];
            foreach ($regionData as $row) {
                $key = $row['team_name'] . '|' . $row['game_type'];
                if (!isset($teamRegions[$key])) {
                    $teamRegions[$key] = [
                        'team_name' => $row['team_name'],
                        'game_type' => $row['game_type'],
                        'region' => $row['match_region'],
                        'match_count' => $row['match_count']
                    ];
                }
            }

            // Paso 3: Actualizar equipos
            foreach ($teamRegions as $data) {
                $existing = $this->teamModel->getTeamByNameAndGame($data['team_name'], $data['game_type']);

                if ($existing) {
                    // Solo actualizar si tiene 'Other' o NULL
                    if ($existing['region'] === 'Other' || $existing['region'] === null) {
                        $this->teamModel->updateTeam($existing['id'], [
                            'region' => $data['region'],
                            'country' => $existing['country'],
                            'logo_url' => $existing['logo_url'],
                            'description' => $existing['description'],
                            'liquipedia_url' => $existing['liquipedia_url']
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    // Crear nuevo equipo
                    $this->teamModel->saveTeam([
                        'name' => $data['team_name'],
                        'game_type' => $data['game_type'],
                        'region' => $data['region']
                    ]);
                    $created++;
                }
            }

            // Set session message
            $message = "✅ Regiones sincronizadas: $updated actualizados, $created creados";
            if ($skipped > 0) {
                $message .= " ($skipped ya tenían región)";
            }
            $_SESSION['message'] = $message;
        } catch (Exception $e) {
            $_SESSION['error'] = "Error sincronizando regiones: " . $e->getMessage();
        }

        // Redirect back to teams page
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/teams';
        if ($gameFilter) {
            $redirectUrl .= '?game=' . $gameFilter;
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Get unique teams from matches that have "Other" as their region
     */
    private function getTeamsWithOtherRegion(?string $gameType = null): array
    {
        $db = $this->teamModel->getConnection();

        $baseQuery = "
            SELECT team1_name as name, game_type, match_region as region FROM matches WHERE match_region = 'Other' OR match_region IS NULL
            UNION
            SELECT team2_name as name, game_type, match_region as region FROM matches WHERE match_region = 'Other' OR match_region IS NULL
        ";

        if ($gameType && $gameType !== 'all') {
            $baseQuery = "
                SELECT team1_name as name, game_type, match_region as region FROM matches WHERE (match_region = 'Other' OR match_region IS NULL) AND game_type = :game_type1
                UNION
                SELECT team2_name as name, game_type, match_region as region FROM matches WHERE (match_region = 'Other' OR match_region IS NULL) AND game_type = :game_type2
            ";
        }

        $sql = "
            SELECT DISTINCT name, game_type 
            FROM ($baseQuery) as teams_other 
            WHERE name IS NOT NULL AND name != '' AND name != 'TBD' AND name != 'TBA'
            ORDER BY name ASC
        ";

        $stmt = $db->prepare($sql);
        if ($gameType && $gameType !== 'all') {
            $stmt->execute(['game_type1' => $gameType, 'game_type2' => $gameType]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update the match_region for all matches involving this team
     */
    private function updateMatchRegionsForTeam(string $teamName, string $gameType, string $region): void
    {
        $db = $this->teamModel->getConnection();

        $stmt = $db->prepare("
            UPDATE matches 
            SET match_region = :region 
            WHERE (team1_name = :team1 OR team2_name = :team2) 
              AND game_type = :game_type 
              AND (match_region = 'Other' OR match_region IS NULL)
        ");

        $stmt->execute([
            'region' => $region,
            'team1' => $teamName,
            'team2' => $teamName,
            'game_type' => $gameType
        ]);
    }
}
