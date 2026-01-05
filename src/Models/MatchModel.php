<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Database;
use App\Classes\MatchPredictor;
use PDO;

class MatchModel
{
    private PDO $db;
    private ?MatchPredictor $predictor = null;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function saveMatches(array $matches): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO matches (
                game_type, team1_name, team2_name, tournament_name, match_time, 
                match_region, team1_score, team2_score, match_status, match_url, 
                ai_prediction, match_importance, vlr_match_id, hltv_match_id
            ) 
            VALUES (
                :game_type, :team1, :team2, :tournament, :time, 
                :region, :team1_score, :team2_score, :status, :url, 
                :prediction, :importance, :vlr_match_id, :hltv_match_id
            )
        ");

        foreach ($matches as $match) {
            $prediction = $this->calculateAiPrediction($match['team1'], $match['team2'], $match['game_type']);

            $stmt->execute([
                ':game_type' => $match['game_type'],
                ':team1' => $match['team1'],
                ':team2' => $match['team2'],
                ':tournament' => $match['tournament'],
                ':time' => $match['time'],
                ':region' => $match['region'] ?? 'Other',
                ':team1_score' => $match['team1_score'] ?? null,
                ':team2_score' => $match['team2_score'] ?? null,
                ':status' => $match['match_status'] ?? 'upcoming',
                ':url' => $match['match_url'] ?? null,
                ':prediction' => $prediction,
                ':importance' => $match['match_importance'] ?? 0,
                ':vlr_match_id' => $match['vlr_match_id'] ?? null,
                ':hltv_match_id' => $match['hltv_match_id'] ?? null
            ]);
        }
    }

    public function getAllMatches(?string $gameType = null, ?string $region = null, ?string $status = null): array
    {
        $sql = "SELECT *, 
                CASE 
                    WHEN match_status = 'live' THEN 1
                    WHEN match_status = 'upcoming' AND match_time >= NOW() THEN 2
                    WHEN DATE(match_time) = CURDATE() THEN 3
                    WHEN match_status = 'upcoming' AND match_time < NOW() THEN 4
                    ELSE 5
                END as sort_priority
                FROM matches";
        $params = [];
        $conditions = [];

        if ($gameType) {
            $conditions[] = "game_type = :game_type";
            $params[':game_type'] = $gameType;
        }

        if ($region && $region !== 'all') {
            $conditions[] = "match_region = :region";
            $params[':region'] = $region;
        }

        if ($status && $status !== 'all') {
            if ($status === 'upcoming') {
                // UPCOMING = status pending AND date is in the future (or today)
                $conditions[] = "(match_status = 'upcoming' AND match_time >= CURDATE())";
            } elseif ($status === 'completed') {
                // COMPLETED = has scores OR past date with 'upcoming' status (missed/postponed)
                $conditions[] = "(match_status IN ('completed', 'live') OR (match_status = 'upcoming' AND match_time < CURDATE()))";
            }
        } else {
            // ANY STATUS: Show today's matches + real upcoming (future) + live
            // Exclude old 'upcoming' matches that are clearly past and irrelevant
            $conditions[] = "(
                match_status = 'live' OR
                (match_status = 'upcoming' AND match_time >= CURDATE()) OR
                (match_status = 'completed' AND match_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
            )";
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        // Sorting: 
        // 1. LIVE matches first
        // 2. Upcoming starting soonest (closest to now)
        // 3. Today's completed
        // 4. Everything else by recency
        if ($status === 'upcoming') {
            $sql .= " ORDER BY match_time ASC, match_importance DESC";
        } elseif ($status === 'completed') {
            $sql .= " ORDER BY match_time DESC, match_importance DESC";
        } else {
            // Default: Priority order (live > upcoming today > today completed > old)
            $sql .= " ORDER BY sort_priority ASC, 
                      CASE WHEN sort_priority <= 3 THEN match_time END ASC,
                      CASE WHEN sort_priority > 3 THEN match_time END DESC,
                      match_importance DESC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getMatchById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM matches WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function updateMatchDetails(int $id, array $details): void
    {
        $stmt = $this->db->prepare("UPDATE matches SET match_details = :details WHERE id = :id");
        $stmt->execute([
            ':details' => json_encode($details),
            ':id' => $id
        ]);
    }

    public function deleteAllMatches(): void
    {
        // Disable foreign key checks to allow truncation
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->db->exec("TRUNCATE TABLE player_stats");
        $this->db->exec("TRUNCATE TABLE matches");
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    /**
     * Get the count of matches in the database
     */
    public function getMatchCount(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM matches");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Check if we have cached matches (at least some data in DB)
     */
    public function hasCachedMatches(): bool
    {
        return $this->getMatchCount() > 0;
    }

    /**
     * Get the most recent match_time from the database
     * This helps determine if data is "fresh" enough
     */
    public function getNewestMatchTime(): ?string
    {
        $stmt = $this->db->query("SELECT MAX(match_time) FROM matches");
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    /**
     * Actualiza el vlr_match_id de un partido
     */
    public function updateVlrMatchId(int $id, string $vlrMatchId): void
    {
        $stmt = $this->db->prepare("UPDATE matches SET vlr_match_id = :vlr_id WHERE id = :id");
        $stmt->execute([':vlr_id' => $vlrMatchId, ':id' => $id]);
    }

    /**
     * Actualiza el hltv_match_id de un partido
     */
    public function updateHltvMatchId(int $id, string $hltvMatchId): void
    {
        $stmt = $this->db->prepare("UPDATE matches SET hltv_match_id = :hltv_id WHERE id = :id");
        $stmt->execute([':hltv_id' => $hltvMatchId, ':id' => $id]);
    }

    /**
     * Obtiene partidos de Valorant sin vlr_match_id (para enriquecer)
     */
    public function getValorantMatchesWithoutVlr(): array
    {
        $sql = "SELECT * FROM matches WHERE game_type = 'valorant' AND vlr_match_id IS NULL AND match_status = 'completed'";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene partidos de CS2 sin hltv_match_id (para enriquecer)
     */
    public function getCs2MatchesWithoutHltv(): array
    {
        $sql = "SELECT * FROM matches WHERE game_type = 'cs2' AND hltv_match_id IS NULL AND match_status = 'completed'";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la conexión PDO para uso en modelos relacionados
     */
    public function getConnection(): PDO
    {
        return $this->db;
    }

    /**
     * Calculate AI prediction for a match using ELO + Head-to-Head
     * 
     * @param string $team1 First team name
     * @param string $team2 Second team name
     * @param string $gameType Game type (valorant, lol, cs2)
     * @return float Win probability for team1 (0-100)
     */
    private function calculateAiPrediction(string $team1, string $team2, string $gameType = 'valorant'): float
    {
        if ($this->predictor === null) {
            $this->predictor = new MatchPredictor($this->db);
        }

        return $this->predictor->predictMatch($team1, $team2, $gameType);
    }

    /**
     * Get the MatchPredictor instance
     */
    public function getPredictor(): MatchPredictor
    {
        if ($this->predictor === null) {
            $this->predictor = new MatchPredictor($this->db);
        }
        return $this->predictor;
    }
}
