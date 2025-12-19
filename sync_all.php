<?php
/**
 * Proactive Sync Script - Syncs all teams and players from matches
 * 
 * Usage:
 *   php sync_all.php                    # Sync all games
 *   php sync_all.php --game=valorant    # Sync only Valorant
 *   php sync_all.php --limit=50         # Limit to 50 teams
 *   php sync_all.php --players-only     # Only sync players from stats
 *   php sync_all.php --delay=3          # 3 seconds delay between requests
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\TeamScraper;
use App\Classes\PlayerScraper;
use App\Models\TeamModel;
use App\Models\PlayerModel;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
} catch (Exception $e) {
    // Continue without .env
}

// Parse command line arguments
$options = getopt('', ['game:', 'limit:', 'delay:', 'players-only', 'teams-only', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
    
PROACTIVE SYNC SCRIPT - ClutchData
===================================

Usage: php sync_all.php [options]

Options:
  --game=GAME       Only sync specified game (valorant, lol, cs2)
  --limit=N         Limit to N teams per game (default: 100)
  --delay=N         Seconds delay between requests (default: 2)
  --players-only    Only sync players from match stats
  --teams-only      Only sync teams (skip roster scraping)
  --help            Show this help message

Examples:
  php sync_all.php --game=valorant --limit=10
  php sync_all.php --players-only
  php sync_all.php --delay=3 --limit=50


HELP;
    exit(0);
}

$gameFilter = $options['game'] ?? null;
$limit = isset($options['limit']) ? (int) $options['limit'] : 100;
$delay = isset($options['delay']) ? (int) $options['delay'] : 2;
$playersOnly = isset($options['players-only']);
$teamsOnly = isset($options['teams-only']);

// Initialize models
$teamModel = new TeamModel();
$playerModel = new PlayerModel();

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           CLUTCHDATA - PROACTIVE SYNC SCRIPT               ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$startTime = microtime(true);

// ============================================
// STEP 1: Sync Players from Match Stats
// ============================================
if (!$teamsOnly) {
    echo "┌────────────────────────────────────────────────────────────┐\n";
    echo "│  STEP 1: Syncing players from match statistics...         │\n";
    echo "└────────────────────────────────────────────────────────────┘\n";

    $syncedPlayers = syncPlayersFromStats($playerModel, $gameFilter);
    echo "  ✓ Synced $syncedPlayers players from match stats\n\n";
}

// ============================================
// STEP 2: Sync Teams from Matches
// ============================================
if (!$playersOnly) {
    echo "┌────────────────────────────────────────────────────────────┐\n";
    echo "│  STEP 2: Syncing teams from matches...                    │\n";
    echo "└────────────────────────────────────────────────────────────┘\n";

    $teamScraper = new TeamScraper();
    $syncedTeams = 0;
    $syncedRosters = 0;
    $failed = 0;

    // Get teams from matches
    $matchTeams = $teamModel->getTeamsFromMatches($gameFilter);
    $totalTeams = min(count($matchTeams), $limit);

    echo "  Found " . count($matchTeams) . " unique teams, processing $totalTeams...\n\n";

    $processed = 0;
    foreach ($matchTeams as $teamData) {
        if ($processed >= $limit)
            break;

        $teamName = $teamData['name'];
        $gameType = $teamData['game_type'];

        // Skip placeholders
        if (in_array(strtoupper($teamName), ['TBD', 'TBA', 'UNKNOWN', '???', ''])) {
            continue;
        }

        // Check if already fully synced
        $existing = $teamModel->getTeamByNameAndGame($teamName, $gameType);
        if ($existing && !empty($existing['logo_url']) && !empty($existing['description'])) {
            // Already synced with full details
            $processed++;
            continue;
        }

        $processed++;
        $progress = str_pad("[$processed/$totalTeams]", 12);

        echo "  $progress Scraping: $teamName ($gameType)... ";

        try {
            $scrapedData = $teamScraper->scrapeTeam($teamName, $gameType);

            if ($scrapedData) {
                // Save team
                $teamId = $teamModel->saveTeam([
                    'name' => $scrapedData['name'],
                    'game_type' => $scrapedData['game_type'],
                    'region' => $scrapedData['region'],
                    'country' => $scrapedData['country'],
                    'logo_url' => $scrapedData['logo_url'],
                    'description' => $scrapedData['description'],
                    'liquipedia_url' => $scrapedData['liquipedia_url']
                ]);

                $syncedTeams++;
                $rosterCount = 0;

                // Save roster players (if not teams-only mode)
                if (!$teamsOnly && !empty($scrapedData['roster'])) {
                    foreach ($scrapedData['roster'] as $playerData) {
                        $playerModel->savePlayer([
                            'nickname' => $playerData['nickname'],
                            'game_type' => $gameType,
                            'team_id' => $teamId,
                            'role' => $playerData['role'] ?? null,
                            'country' => $playerData['country'] ?? null,
                            'liquipedia_url' => $playerData['liquipedia_url'] ?? null
                        ]);
                        $rosterCount++;
                        $syncedRosters++;
                    }
                }

                echo "✓ (roster: $rosterCount players)\n";
            } else {
                echo "✗ (no data found)\n";
                $failed++;
            }

            // Rate limiting
            if ($processed < $totalTeams) {
                sleep($delay);
            }

        } catch (Exception $e) {
            echo "✗ Error: " . substr($e->getMessage(), 0, 40) . "\n";
            $failed++;
        }
    }

    echo "\n  Summary:\n";
    echo "  ├─ Teams synced: $syncedTeams\n";
    echo "  ├─ Players from rosters: $syncedRosters\n";
    echo "  └─ Failed: $failed\n\n";
}

// ============================================
// FINAL SUMMARY
// ============================================
$elapsed = round(microtime(true) - $startTime, 2);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    SYNC COMPLETE                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

// Get final counts
$db = $teamModel->getConnection();
$teamCount = $db->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$playerCount = $db->query("SELECT COUNT(*) FROM players")->fetchColumn();

echo "  Database totals:\n";
echo "  ├─ Teams:   $teamCount\n";
echo "  ├─ Players: $playerCount\n";
echo "  └─ Time:    {$elapsed}s\n\n";

/**
 * Sync players from player_stats table
 */
function syncPlayersFromStats(PlayerModel $playerModel, ?string $gameFilter): int
{
    $db = $playerModel->getConnection();

    // Build query to get unique players from stats
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

    $synced = 0;
    foreach ($statsPlayers as $player) {
        // Check if player already exists
        $existing = $playerModel->getPlayerByNickname($player['nickname'], $player['game_type']);

        if (!$existing) {
            // Find team ID if available
            $teamId = null;
            if (!empty($player['team_name'])) {
                $teamStmt = $db->prepare("SELECT id FROM teams WHERE name = :name AND game_type = :game_type LIMIT 1");
                $teamStmt->execute(['name' => $player['team_name'], 'game_type' => $player['game_type']]);
                $team = $teamStmt->fetch(\PDO::FETCH_ASSOC);
                $teamId = $team['id'] ?? null;
            }

            $playerModel->savePlayer([
                'nickname' => $player['nickname'],
                'game_type' => $player['game_type'],
                'team_id' => $teamId
            ]);
            $synced++;
        }
    }

    return $synced;
}
