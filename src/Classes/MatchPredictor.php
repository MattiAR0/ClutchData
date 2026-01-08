<?php

declare(strict_types=1);

namespace App\Classes;

use App\Models\TeamModel;
use PDO;
use Monolog\Logger as MonologLogger;

/**
 * Match Predictor using ELO Rating System + Head-to-Head Analysis
 * 
 * The ELO system calculates team strength based on:
 * 1. Base ELO rating (1500 initial)
 * 2. Historical match results
 * 3. Head-to-head record between the two specific teams
 */
class MatchPredictor
{
    private PDO $db;
    private TeamModel $teamModel;
    private GeminiPredictor $geminiPredictor;
    private ?MonologLogger $logger;

    // ELO Configuration
    private const BASE_RATING = 1500;
    private const K_FACTOR = 32;           // How much ratings change per match
    private const H2H_WEIGHT = 0.25;       // Weight given to head-to-head record (0-1)
    private const MIN_MATCHES_FOR_H2H = 2; // Minimum H2H matches to consider

    public function __construct(?PDO $db = null, ?MonologLogger $logger = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->teamModel = new TeamModel($this->db);
        $this->logger = $logger;
        $this->geminiPredictor = new GeminiPredictor(null, $logger);
    }

    /**
     * Predict win probability for team1 against team2 using ELO (fallback method)
     * Returns percentage (0-100) for team1 winning
     */
    public function predictMatch(string $team1, string $team2, string $gameType): float
    {
        // Get or calculate ELO ratings
        $elo1 = $this->getTeamElo($team1, $gameType);
        $elo2 = $this->getTeamElo($team2, $gameType);

        // Calculate base probability from ELO
        $eloProbability = $this->calculateEloProbability($elo1, $elo2);

        // Get head-to-head adjustment
        $h2hAdjustment = $this->getHeadToHeadAdjustment($team1, $team2, $gameType);

        // Combine ELO probability with H2H
        // If H2H data is available, blend it with ELO
        if ($h2hAdjustment !== null) {
            $finalProbability = (1 - self::H2H_WEIGHT) * $eloProbability + self::H2H_WEIGHT * $h2hAdjustment;
        } else {
            $finalProbability = $eloProbability;
        }

        // Clamp between 5% and 95% (no team is ever a "sure thing")
        return max(5.0, min(95.0, $finalProbability * 100));
    }

    /**
     * Predict match using Gemini AI with ELO fallback
     * Returns array with prediction, explanation, and source
     */
    public function predictMatchWithAI(array $matchData): array
    {
        $team1 = $matchData['team1'] ?? $matchData['team1_name'] ?? '';
        $team2 = $matchData['team2'] ?? $matchData['team2_name'] ?? '';
        $gameType = $matchData['game_type'] ?? 'valorant';

        // Get team stats for context
        $team1Stats = $this->getTeamStats($team1, $gameType);
        $team2Stats = $this->getTeamStats($team2, $gameType);

        // Get detailed recent match history
        $team1RecentDetails = $this->getRecentMatchDetails($team1, $gameType);
        $team2RecentDetails = $this->getRecentMatchDetails($team2, $gameType);

        // Get advanced team stats (if available)
        $team1AdvancedStats = $this->getTeamAverageStats($team1, $gameType);
        $team2AdvancedStats = $this->getTeamAverageStats($team2, $gameType);

        $h2hRecord = $this->getH2HRecord($team1, $team2, $gameType);

        // Try Gemini first
        if ($this->geminiPredictor->isConfigured()) {
            $result = $this->geminiPredictor->analyzeMatch(
                $matchData,
                $team1Stats,
                $team2Stats,
                $h2hRecord,
                $team1RecentDetails,
                $team2RecentDetails,
                $team1AdvancedStats,
                $team2AdvancedStats
            );

            if ($result['source'] === 'gemini') {
                return $result;
            }
        }

        // Fallback to ELO
        $eloPrediction = $this->predictMatch($team1, $team2, $gameType);
        return [
            'prediction' => $eloPrediction,
            'explanation' => "Predicción basada en rating ELO ({$team1Stats['elo']} vs {$team2Stats['elo']}) y estadísticas históricas.",
            'source' => 'elo'
        ];
    }

    /**
     * Get detailed recent matches for prompt context
     */
    private function getRecentMatchDetails(string $team, string $gameType, int $limit = 5): array
    {
        $matches = $this->getCompletedMatchesForTeam($team, $gameType);
        $details = [];

        // Take only the last $limit matches
        $recentMatches = array_slice(array_reverse($matches), 0, $limit); // matches are usually ASC in internal method, so reverse for DESC

        foreach ($recentMatches as $match) {
            $isTeam1 = strtolower($match['team1_name']) === strtolower($team);
            $opponent = $isTeam1 ? $match['team2_name'] : $match['team1_name'];
            $score1 = (int) ($match['team1_score'] ?? 0);
            $score2 = (int) ($match['team2_score'] ?? 0);

            $myScore = $isTeam1 ? $score1 : $score2;
            $opScore = $isTeam1 ? $score2 : $score1;

            $result = ($myScore > $opScore) ? 'Win' : 'Loss';
            if ($myScore === $opScore)
                $result = 'Draw';

            $details[] = sprintf(
                "vs %s: %s %d-%d",
                $opponent,
                $result,
                $myScore,
                $opScore
            );
        }

        return $details;
    }

    /**
     * Get average advanced stats for a team (if available in player_stats)
     */
    private function getTeamAverageStats(string $team, string $gameType): array
    {
        try {
            // Check if player_stats has data for this team
            // We join with matches to ensure game_type matches
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(ps.rating) as avg_rating,
                    AVG(ps.acs) as avg_acs,
                    AVG(ps.kast) as avg_kast
                FROM player_stats ps
                JOIN matches m ON ps.match_id = m.id
                WHERE ps.team_name = :team
                  AND m.game_type = :game_type
                  AND ps.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");

            $stmt->execute([':team' => $team, ':game_type' => $gameType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || $result['avg_rating'] === null) {
                return [];
            }

            $stats = [];
            if ($result['avg_rating'])
                $stats['avg_rating'] = number_format((float) $result['avg_rating'], 2);
            if ($result['avg_acs'])
                $stats['avg_acs'] = round((float) $result['avg_acs']);
            if ($result['avg_kast'])
                $stats['avg_kast'] = number_format((float) $result['avg_kast'], 1) . '%';

            return $stats;
        } catch (\Exception $e) {
            // Table might not exist or other error
            return [];
        }
    }

    /**
     * Get team statistics for AI context
     */
    private function getTeamStats(string $teamName, string $gameType): array
    {
        $matches = $this->getCompletedMatchesForTeam($teamName, $gameType);
        $wins = 0;
        $losses = 0;

        foreach ($matches as $match) {
            $isTeam1 = strtolower($match['team1_name']) === strtolower($teamName);
            $score1 = (int) ($match['team1_score'] ?? 0);
            $score2 = (int) ($match['team2_score'] ?? 0);

            if ($score1 === $score2)
                continue;

            $won = ($isTeam1 && $score1 > $score2) || (!$isTeam1 && $score2 > $score1);
            if ($won)
                $wins++;
            else
                $losses++;
        }

        $total = $wins + $losses;
        return [
            'recent_matches' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $total > 0 ? $wins / $total : 0.5,
            'elo' => $this->getTeamElo($teamName, $gameType)
        ];
    }

    /**
     * Get head-to-head record for AI context
     */
    private function getH2HRecord(string $team1, string $team2, string $gameType): array
    {
        $stmt = $this->db->prepare("
            SELECT team1_name, team2_name, team1_score, team2_score
            FROM matches
            WHERE game_type = :game_type
              AND match_status = 'completed'
              AND team1_score IS NOT NULL
              AND (
                  (LOWER(team1_name) = LOWER(:t1a) AND LOWER(team2_name) = LOWER(:t2a))
                  OR (LOWER(team1_name) = LOWER(:t2b) AND LOWER(team2_name) = LOWER(:t1b))
              )
            LIMIT 20
        ");

        $stmt->execute([
            ':game_type' => $gameType,
            ':t1a' => $team1,
            ':t2a' => $team2,
            ':t1b' => $team1,
            ':t2b' => $team2
        ]);

        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $team1Wins = 0;
        $team2Wins = 0;

        foreach ($matches as $match) {
            $s1 = (int) $match['team1_score'];
            $s2 = (int) $match['team2_score'];
            if ($s1 === $s2)
                continue;

            if (strtolower($match['team1_name']) === strtolower($team1)) {
                $s1 > $s2 ? $team1Wins++ : $team2Wins++;
            } else {
                $s2 > $s1 ? $team1Wins++ : $team2Wins++;
            }
        }

        return ['team1_wins' => $team1Wins, 'team2_wins' => $team2Wins];
    }

    /**
     * Get team's ELO rating (from cache or calculate)
     */
    public function getTeamElo(string $teamName, string $gameType): int
    {
        // Try to get cached rating from teams table
        $team = $this->teamModel->getTeamByNameAndGame($teamName, $gameType);

        if ($team && !empty($team['elo_rating'])) {
            return (int) $team['elo_rating'];
        }

        // Calculate ELO from match history
        return $this->calculateEloFromHistory($teamName, $gameType);
    }

    /**
     * Calculate ELO rating based on match history
     */
    private function calculateEloFromHistory(string $teamName, string $gameType): int
    {
        $matches = $this->getCompletedMatchesForTeam($teamName, $gameType);

        if (empty($matches)) {
            return self::BASE_RATING;
        }

        $rating = self::BASE_RATING;

        // Process matches chronologically
        foreach ($matches as $match) {
            $isTeam1 = (strtolower($match['team1_name']) === strtolower($teamName));
            $opponent = $isTeam1 ? $match['team2_name'] : $match['team1_name'];

            // Get opponent's approximate rating (use base if unknown)
            $opponentRating = self::BASE_RATING;

            // Determine if this team won
            $score1 = (int) ($match['team1_score'] ?? 0);
            $score2 = (int) ($match['team2_score'] ?? 0);

            if ($score1 === $score2) {
                continue; // Skip draws
            }

            $won = ($isTeam1 && $score1 > $score2) || (!$isTeam1 && $score2 > $score1);

            // Calculate expected score
            $expected = $this->calculateEloProbability($rating, $opponentRating);

            // Actual score (1 for win, 0 for loss)
            $actual = $won ? 1.0 : 0.0;

            // Update rating
            $rating += (int) round(self::K_FACTOR * ($actual - $expected));
        }

        // Clamp rating to reasonable bounds
        return max(800, min(2400, $rating));
    }

    /**
     * Calculate probability using ELO formula
     * Returns probability (0-1) that team with rating1 beats team with rating2
     */
    private function calculateEloProbability(int $rating1, int $rating2): float
    {
        return 1.0 / (1.0 + pow(10, ($rating2 - $rating1) / 400.0));
    }

    /**
     * Get head-to-head win rate adjustment
     * Returns null if not enough H2H data
     */
    private function getHeadToHeadAdjustment(string $team1, string $team2, string $gameType): ?float
    {
        $stmt = $this->db->prepare("
            SELECT 
                team1_name, team2_name, team1_score, team2_score
            FROM matches
            WHERE game_type = :game_type
              AND match_status = 'completed'
              AND team1_score IS NOT NULL
              AND team2_score IS NOT NULL
              AND (
                  (LOWER(team1_name) = LOWER(:t1a) AND LOWER(team2_name) = LOWER(:t2a))
                  OR (LOWER(team1_name) = LOWER(:t2b) AND LOWER(team2_name) = LOWER(:t1b))
              )
            ORDER BY match_time DESC
            LIMIT 10
        ");

        $stmt->execute([
            ':game_type' => $gameType,
            ':t1a' => $team1,
            ':t2a' => $team2,
            ':t1b' => $team1,
            ':t2b' => $team2
        ]);

        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($matches) < self::MIN_MATCHES_FOR_H2H) {
            return null;
        }

        $team1Wins = 0;
        $totalMatches = 0;

        foreach ($matches as $match) {
            $score1 = (int) $match['team1_score'];
            $score2 = (int) $match['team2_score'];

            if ($score1 === $score2) {
                continue;
            }

            $totalMatches++;

            // Check if team1 (our target) won this match
            if (strtolower($match['team1_name']) === strtolower($team1)) {
                if ($score1 > $score2) {
                    $team1Wins++;
                }
            } else {
                // team1 was listed as team2 in this match
                if ($score2 > $score1) {
                    $team1Wins++;
                }
            }
        }

        if ($totalMatches === 0) {
            return null;
        }

        return $team1Wins / $totalMatches;
    }

    /**
     * Get completed matches for a team, ordered chronologically
     */
    private function getCompletedMatchesForTeam(string $teamName, string $gameType): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM matches
            WHERE game_type = :game_type
              AND match_status = 'completed'
              AND team1_score IS NOT NULL
              AND team2_score IS NOT NULL
              AND (LOWER(team1_name) = LOWER(:team1) OR LOWER(team2_name) = LOWER(:team2))
            ORDER BY match_time ASC
            LIMIT 50
        ");

        $stmt->execute([
            ':game_type' => $gameType,
            ':team1' => $teamName,
            ':team2' => $teamName
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update cached ELO rating for a team
     */
    public function updateTeamEloCache(string $teamName, string $gameType): void
    {
        $elo = $this->calculateEloFromHistory($teamName, $gameType);

        $stmt = $this->db->prepare("
            UPDATE teams 
            SET elo_rating = :elo, elo_updated_at = CURRENT_TIMESTAMP
            WHERE LOWER(name) = LOWER(:name) AND game_type = :game_type
        ");

        $stmt->execute([
            ':elo' => $elo,
            ':name' => $teamName,
            ':game_type' => $gameType
        ]);
    }

    /**
     * Recalculate and cache ELO for all teams
     */
    public function recalculateAllRatings(): array
    {
        $stats = ['updated' => 0, 'teams' => []];

        // Get all unique teams from matches
        $stmt = $this->db->query("
            SELECT DISTINCT team1_name as name, game_type FROM matches
            UNION
            SELECT DISTINCT team2_name as name, game_type FROM matches
        ");

        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($teams as $team) {
            if (empty($team['name']) || $team['name'] === 'TBD') {
                continue;
            }

            $elo = $this->calculateEloFromHistory($team['name'], $team['game_type']);

            // Try to update in teams table
            $updateStmt = $this->db->prepare("
                UPDATE teams 
                SET elo_rating = :elo, elo_updated_at = CURRENT_TIMESTAMP
                WHERE LOWER(name) = LOWER(:name) AND game_type = :game_type
            ");

            $updateStmt->execute([
                ':elo' => $elo,
                ':name' => $team['name'],
                ':game_type' => $team['game_type']
            ]);

            if ($updateStmt->rowCount() > 0) {
                $stats['updated']++;
            }

            $stats['teams'][] = [
                'name' => $team['name'],
                'game' => $team['game_type'],
                'elo' => $elo
            ];
        }

        return $stats;
    }

    /**
     * Recalculate predictions for all upcoming matches
     */
    public function updateAllMatchPredictions(): int
    {
        $stmt = $this->db->query("
            SELECT id, team1_name, team2_name, game_type 
            FROM matches 
            WHERE match_status = 'upcoming'
        ");

        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $updated = 0;

        foreach ($matches as $match) {
            $prediction = $this->predictMatch(
                $match['team1_name'],
                $match['team2_name'],
                $match['game_type']
            );

            $updateStmt = $this->db->prepare("
                UPDATE matches SET ai_prediction = :prediction WHERE id = :id
            ");

            $updateStmt->execute([
                ':prediction' => $prediction,
                ':id' => $match['id']
            ]);

            $updated++;
        }

        return $updated;
    }
}
