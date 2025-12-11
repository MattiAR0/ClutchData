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
            INSERT INTO matches (game_type, team1_name, team2_name, tournament_name, match_time, match_region, ai_prediction) 
            VALUES (:game_type, :team1, :team2, :tournament, :time, :region, :prediction)
        ");

        foreach ($matches as $match) {
            // Predicción "AI": Genera un valor aleatorio entre 0.0 y 100.0
            // En una app real, esto llamaría a un servicio de ML o usaría estadísticas históricas.
            $prediction = $this->calculateAiPrediction($match['team1'], $match['team2']);

            $stmt->execute([
                ':game_type' => $match['game_type'],
                ':team1' => $match['team1'],
                ':team2' => $match['team2'],
                ':tournament' => $match['tournament'],
                ':time' => $match['time'],
                ':region' => $match['region'] ?? 'Other',
                ':prediction' => $prediction
            ]);
        }
    }

    public function getAllMatches(?string $gameType = null, ?string $region = null): array
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

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY match_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function deleteAllMatches(): void
    {
        $this->db->exec("TRUNCATE TABLE matches");
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
