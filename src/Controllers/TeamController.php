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

        $activeTab = $gameType ?? $_GET['game'] ?? 'all';
        $activeRegion = $_GET['region'] ?? 'all';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;

        // Update active tab logic to use the resolved game type
        $gameType = $activeTab;

        try {
            $search = $_GET['q'] ?? null;

            // Get filtered teams count
            $totalTeams = $this->teamModel->getTotalCount(
                $gameType !== 'all' ? $gameType : null,
                $activeRegion !== 'all' ? $activeRegion : null,
                $search
            );

            // Get teams from database with pagination
            $teams = $this->teamModel->getAllTeams(
                $gameType !== 'all' ? $gameType : null,
                $activeRegion !== 'all' ? $activeRegion : null,
                $search,
                $limit,
                $offset
            );

            // SEARCH DISCOVERY LOGIC
            // If user is searching and we found very few results locally, try to discover more from Liquipedia
            // We do this if we are on a specific game, OR if we are on 'all' (we check all games)
            if ($search && ($totalTeams < 5)) {
                try {
                    $scraper = new \App\Classes\TeamScraper();
                    // Normalize search term for Liquipedia (Capitalize first letter usually works best)
                    $normalizedSearch = ucfirst(trim($search));

                    // Determine which games to scrape
                    $gamesToScrape = ($gameType !== 'all') ? [$gameType] : ['valorant', 'cs2', 'lol'];
                    $foundNew = false;

                    foreach ($gamesToScrape as $gType) {
                        // scrapeTeamList with startFrom = normalized search term
                        // We disable limit implicitly by using scrapeTeamList which fetches one batch
                        $newTeams = $scraper->scrapeTeamList($gType, $normalizedSearch);

                        if (!empty($newTeams)) {
                            foreach ($newTeams as $teamData) {
                                // Only save if it doesn't exist to avoid overwriting full data with minimal list data
                                $existing = $this->teamModel->getTeamByNameAndGame($teamData['name'], $gType);
                                if (!$existing) {
                                    $this->teamModel->saveTeam($teamData);
                                    $foundNew = true;
                                }
                            }
                        }
                    }

                    if ($foundNew) {
                        // Re-fetch counts and teams
                        $totalTeams = $this->teamModel->getTotalCount($gameType !== 'all' ? $gameType : null, $activeRegion !== 'all' ? $activeRegion : null, $search);
                        $teams = $this->teamModel->getAllTeams($gameType !== 'all' ? $gameType : null, $activeRegion !== 'all' ? $activeRegion : null, $search, $limit, $offset);
                    }

                } catch (Exception $e) {
                    // Silently fail search discovery to not break the page
                    error_log("Search discovery failed: " . $e->getMessage());
                }
            }

            // Calculate total pages
            $totalPages = ceil($totalTeams / $limit);

            // LAZY SCRAPING LOGIC
            // If we are on a specific game view (not search, not 'all' games), and we hit the end of local data
            // We should try to fetch the next batch from Liquipedia
            if (empty($search) && $gameType !== 'all' && ($page >= $totalPages || count($teams) < $limit)) {

                // If it's the very first load (no teams at all), discoverTeams handles it (starts from A)
                // If we have some teams, we get the last one to use as offset
                if ($totalTeams === 0) {
                    $this->discoverTeams($gameType);
                    // Refresh stats after discovery
                    $totalTeams = $this->teamModel->getTotalCount($gameType !== 'all' ? $gameType : null, $activeRegion !== 'all' ? $activeRegion : null);
                    $totalPages = ceil($totalTeams / $limit);
                    $teams = $this->teamModel->getAllTeams($gameType !== 'all' ? $gameType : null, $activeRegion !== 'all' ? $activeRegion : null, null, $limit, $offset);
                } else {
                    // We have teams, but reached the end. Try to fetch more starting from the last known team.
                    // Only do this if we are actually viewing the last page to avoid random triggers
                    if ($page >= $totalPages) {
                        $lastTeamName = $this->teamModel->getLastTeamName($gameType);
                        if ($lastTeamName) {
                            $scraper = new \App\Classes\TeamScraper();
                            $newTeams = $scraper->scrapeTeamList($gameType, $lastTeamName);

                            if (!empty($newTeams)) {
                                $countNew = 0;
                                foreach ($newTeams as $teamData) {
                                    $this->teamModel->saveTeam($teamData);
                                    $countNew++;
                                }

                                // Optimization: If we found new teams, re-query to show them immediately
                                if ($countNew > 0) {
                                    $totalTeams = $this->teamModel->getTotalCount($gameType !== 'all' ? $gameType : null, $activeRegion !== 'all' ? $activeRegion : null);
                                    $teams = $this->teamModel->getAllTeams($gameType !== 'all' ? $gameType : null, $activeRegion !== 'all' ? $activeRegion : null, null, $limit, $offset);
                                    $totalPages = ceil($totalTeams / $limit);
                                }
                            }
                        }
                    }
                }
            }

            // Fallback for empty DB on 'all' view or search
            if (empty($teams) && $totalTeams === 0 && empty($search)) {
                $matchTeams = $this->teamModel->getTeamsFromMatches($gameType !== 'all' ? $gameType : null);
                // Apply manual array slicing for pagination since this is a fallback array
                $totalTeams = count($matchTeams);
                $totalPages = ceil($totalTeams / $limit);
                $matchTeams = array_slice($matchTeams, $offset, $limit);

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
            $totalPages = 0;
            $totalTeams = 0;
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
        // Extend execution time for long-running sync operations
        set_time_limit(0);

        $gameFilter = $_GET['game'] ?? null;
        $limit = (int) ($_GET['limit'] ?? 10); // Default to 10 teams per sync to avoid timeout

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

    /**
     * Discover teams from Liquipedia Category pages (Bulk Import)
     */
    public function discover(): void
    {
        $gameType = $_GET['game'] ?? 'valorant';

        try {
            $this->discoverTeams($gameType);
            $_SESSION['message'] = "✅ Team discovery completed for " . ucfirst($gameType);
        } catch (Exception $e) {
            $_SESSION['error'] = "Discovery failed: " . $e->getMessage();
        }

        // Redirect back
        $redirectUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) . '/teams?game=' . $gameType;
        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * Logic to discover teams and save them
     */
    private function discoverTeams(string $gameType): void
    {
        $scraper = new TeamScraper();
        $discoveredTeams = $scraper->scrapeTeamList($gameType);
        $count = 0;

        foreach ($discoveredTeams as $teamData) {
            // Check if exists
            $existing = $this->teamModel->getTeamByNameAndGame($teamData['name'], $gameType);

            if (!$existing) {
                $this->teamModel->saveTeam($teamData);
                $count++;
            }
        }
    }
}
