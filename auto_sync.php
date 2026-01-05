<?php

/**
 * Automated Full Sync Script for ClutchData
 * 
 * Syncs: Matches → Teams → Players → Predictions
 * 
 * Usage:
 *   php auto_sync.php              # Full sync
 *   php auto_sync.php --matches    # Only matches
 *   php auto_sync.php --teams      # Only teams/players
 *   php auto_sync.php --quiet      # Silent mode (for scheduler)
 * 
 * To automate with Windows Task Scheduler, use: run_sync.bat
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;
use App\Classes\TeamScraper;
use App\Classes\MatchPredictor;
use App\Classes\Database;
use App\Models\MatchModel;
use App\Models\TeamModel;
use App\Models\PlayerModel;

// Parse command line arguments
$options = getopt('', ['matches', 'teams', 'quiet', 'help', 'force']);

if (isset($options['help'])) {
    echo <<<HELP
ClutchData Automatic Sync Script

Usage: php auto_sync.php [options]

Options:
  --matches    Only sync matches (skip teams/players)
  --teams      Only sync teams and players (skip matches)
  --quiet      Suppress output (for scheduled tasks)
  --force      Force full refresh (delete existing data first)
  --help       Show this help message

Examples:
  php auto_sync.php              # Full sync
  php auto_sync.php --quiet      # Silent mode for Task Scheduler
  php auto_sync.php --force      # Force complete refresh

HELP;
    exit(0);
}

$matchesOnly = isset($options['matches']);
$teamsOnly = isset($options['teams']);
$quiet = isset($options['quiet']);
$force = isset($options['force']);

// If neither flag set, do both
if (!$matchesOnly && !$teamsOnly) {
    $matchesOnly = true;
    $teamsOnly = true;
}

function output($msg, $quiet)
{
    if (!$quiet) echo $msg;
}

$startTime = microtime(true);
$stats = [
    'matches' => 0,
    'teams' => 0,
    'players' => 0,
    'predictions' => 0
];

try {
    $db = Database::getInstance()->getConnection();
    $matchModel = new MatchModel();

    output("╔══════════════════════════════════════════════════════════════╗\n", $quiet);
    output("║           ClutchData Automatic Sync                          ║\n", $quiet);
    output("║           " . date('Y-m-d H:i:s') . "                               ║\n", $quiet);
    output("╠══════════════════════════════════════════════════════════════╣\n", $quiet);

    // ═══════════════════════════════════════════════════════════════
    // STEP 1: SYNC MATCHES
    // ═══════════════════════════════════════════════════════════════
    if ($matchesOnly) {
        output("║ [1/3] Syncing Matches...                                     ║\n", $quiet);

        if ($force) {
            $matchModel->deleteAllMatches();
            output("║       ⟳ Cleared existing matches (force mode)                ║\n", $quiet);
        }

        $scrapers = [
            new ValorantScraper(),
            new LolScraper(),
            new Cs2Scraper()
        ];

        foreach ($scrapers as $scraper) {
            $gameType = $scraper->getGameType();
            output("║       → Scraping $gameType...                                   ", $quiet);

            try {
                $data = $scraper->scrapeMatches();
                if (!empty($data)) {
                    // Only save if force mode, or check for new matches
                    if ($force) {
                        $matchModel->saveMatches($data);
                        $stats['matches'] += count($data);
                    } else {
                        // Incremental: only add matches we don't have
                        $newMatches = filterNewMatches($data, $matchModel, $db);
                        if (!empty($newMatches)) {
                            $matchModel->saveMatches($newMatches);
                            $stats['matches'] += count($newMatches);
                        }
                    }
                }
                output("✓\n", $quiet);
            } catch (Exception $e) {
                output("✗\n", $quiet);
                error_log("Sync error for $gameType: " . $e->getMessage());
            }

            sleep(2); // Rate limiting
        }

        output("║       Matches synced: {$stats['matches']}                                   ║\n", $quiet);
    }

    // ═══════════════════════════════════════════════════════════════
    // STEP 2: SYNC TEAMS & PLAYERS
    // ═══════════════════════════════════════════════════════════════
    if ($teamsOnly) {
        output("║ [2/3] Syncing Teams & Players...                             ║\n", $quiet);

        $teamModel = new TeamModel($db);
        $playerModel = new PlayerModel($db);
        $teamScraper = new TeamScraper();

        // Get teams from matches that aren't in teams table yet
        $matchTeams = $teamModel->getTeamsFromMatches();
        $limit = 20; // Process 20 teams per sync to avoid rate limits
        $processed = 0;

        foreach ($matchTeams as $teamData) {
            if ($processed >= $limit) break;

            $teamName = $teamData['name'];
            $gameType = $teamData['game_type'];

            // Skip if already synced
            $existing = $teamModel->getTeamByNameAndGame($teamName, $gameType);
            if ($existing && !empty($existing['logo_url'])) {
                continue;
            }

            if (empty($teamName) || in_array(strtoupper($teamName), ['TBD', 'TBA'])) {
                continue;
            }

            $processed++;

            try {
                $scrapedData = $teamScraper->scrapeTeam($teamName, $gameType);

                if ($scrapedData) {
                    $teamId = $teamModel->saveTeam([
                        'name' => $scrapedData['name'],
                        'game_type' => $gameType,
                        'region' => $scrapedData['region'],
                        'country' => $scrapedData['country'],
                        'logo_url' => $scrapedData['logo_url'],
                        'description' => $scrapedData['description'],
                        'liquipedia_url' => $scrapedData['liquipedia_url']
                    ]);
                    $stats['teams']++;

                    // Sync roster
                    foreach ($scrapedData['roster'] ?? [] as $player) {
                        $playerModel->savePlayer([
                            'nickname' => $player['nickname'],
                            'team_id' => $teamId,
                            'game_type' => $gameType,
                            'role' => $player['role'],
                            'country' => $player['country'],
                            'liquipedia_url' => $player['liquipedia_url'] ?? null
                        ]);
                        $stats['players']++;
                    }
                }

                sleep(2); // Rate limiting
            } catch (Exception $e) {
                error_log("Team sync error for $teamName: " . $e->getMessage());
            }
        }

        output("║       Teams: {$stats['teams']}, Players: {$stats['players']}                              ║\n", $quiet);
    }

    // ═══════════════════════════════════════════════════════════════
    // STEP 3: UPDATE PREDICTIONS
    // ═══════════════════════════════════════════════════════════════
    output("║ [3/3] Updating AI Predictions...                             ║\n", $quiet);

    try {
        $predictor = new MatchPredictor($db);
        $stats['predictions'] = $predictor->updateAllMatchPredictions();
        output("║       Updated {$stats['predictions']} match predictions                         ║\n", $quiet);
    } catch (Exception $e) {
        output("║       ⚠ Prediction update failed                             ║\n", $quiet);
        error_log("Prediction error: " . $e->getMessage());
    }

    // ═══════════════════════════════════════════════════════════════
    // FINAL SUMMARY
    // ═══════════════════════════════════════════════════════════════
    $elapsed = round(microtime(true) - $startTime, 1);

    output("╠══════════════════════════════════════════════════════════════╣\n", $quiet);
    output("║ ✓ Sync Complete                                              ║\n", $quiet);
    output("║   Time: {$elapsed}s                                              ║\n", $quiet);
    output("╚══════════════════════════════════════════════════════════════╝\n", $quiet);

    // Log summary for scheduled runs
    $logEntry = sprintf(
        "[%s] Sync complete: %d matches, %d teams, %d players, %d predictions (%.1fs)\n",
        date('Y-m-d H:i:s'),
        $stats['matches'],
        $stats['teams'],
        $stats['players'],
        $stats['predictions'],
        $elapsed
    );
    file_put_contents(__DIR__ . '/logs/auto_sync.log', $logEntry, FILE_APPEND);
} catch (Exception $e) {
    output("ERROR: " . $e->getMessage() . "\n", $quiet);
    error_log("Auto sync fatal error: " . $e->getMessage());
    exit(1);
}

/**
 * Filter matches that don't already exist in DB
 */
function filterNewMatches(array $matches, MatchModel $model, $db): array
{
    $new = [];

    foreach ($matches as $match) {
        $stmt = $db->prepare("
            SELECT id FROM matches 
            WHERE team1_name = ? AND team2_name = ? AND match_time = ?
            LIMIT 1
        ");
        $stmt->execute([$match['team1'], $match['team2'], $match['time']]);

        if (!$stmt->fetch()) {
            $new[] = $match;
        }
    }

    return $new;
}
