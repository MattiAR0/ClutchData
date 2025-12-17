<?php

namespace App\Models;

use App\Classes\Database;
use PDO;

class MatchModel
{
    private PDO $db;

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
                ai_prediction, match_importance, vlr_match_id
            ) 
            VALUES (
                :game_type, :team1, :team2, :tournament, :time, 
                :region, :team1_score, :team2_score, :status, :url, 
                :prediction, :importance, :vlr_match_id
            )
        ");

        foreach ($matches as $match) {
            $prediction = $this->calculateAiPrediction($match['team1'], $match['team2']);

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
                ':vlr_match_id' => $match['vlr_match_id'] ?? null
            ]);
        }
    }

    public function getAllMatches(?string $gameType = null, ?string $region = null, ?string $status = null): array
    {
        $sql = "SELECT * FROM matches";
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
                $conditions[] = "match_status = 'upcoming'";
            } elseif ($status === 'completed') {
                $conditions[] = "match_status != 'upcoming'"; // Covers 'completed', 'live'
            }
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        // Updated sorting: Importance first.
        // If status is UPCOMING, we want match_time ASC (Nearest first)
        // If status is COMPLETED or ALL, we want match_time DESC (Newest first)

        $dateSort = 'DESC';
        if ($status === 'upcoming') {
            $dateSort = 'ASC';
        }

        $sql .= " ORDER BY match_importance DESC, match_time " . $dateSort;

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
        $this->db->exec("TRUNCATE TABLE matches");
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
     * Obtiene partidos de Valorant sin vlr_match_id (para enriquecer)
     */
    public function getValorantMatchesWithoutVlr(): array
    {
        $sql = "SELECT * FROM matches WHERE game_type = 'valorant' AND vlr_match_id IS NULL AND match_status = 'completed'";
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

    private function calculateAiPrediction(string $team1, string $team2): float
    {
        // "Algoritmo Complejo de Predicción" ;)
        // Hash de los nombres para que la "predicción" sea consistente para el mismo partido
        $hash = crc32($team1 . $team2);

        // Normalizar entre 0 y 100
        $percentage = abs($hash % 10000) / 100;

        return $percentage;
    }
}
