<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Database;
use PDO;
use Exception;

/**
 * Model for managing players in the database
 */
class PlayerModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Get database connection
     */
    public function getConnection(): PDO
    {
        return $this->db;
    }

    /**
     * Get all players, optionally filtered by game type
     */
    public function getAllPlayers(?string $gameType = null, ?int $teamId = null, ?string $search = null, int $limit = 1000, int $offset = 0): array
    {
        $sql = "SELECT p.*, t.name as team_name FROM players p 
                LEFT JOIN teams t ON p.team_id = t.id 
                WHERE 1=1";
        $params = [];

        if ($gameType && $gameType !== 'all') {
            $sql .= " AND p.game_type = :game_type";
            $params['game_type'] = $gameType;
        }

        if ($teamId) {
            $sql .= " AND p.team_id = :team_id";
            $params['team_id'] = $teamId;
        }

        if ($search) {
            $sql .= " AND (p.nickname LIKE :search OR p.real_name LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY p.nickname ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT); // Fix bindValue for integer

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalCount(?string $gameType = null, ?int $teamId = null, ?string $search = null): int
    {
        $sql = "SELECT COUNT(*) FROM players p WHERE 1=1";
        $params = [];

        if ($gameType && $gameType !== 'all') {
            $sql .= " AND p.game_type = :game_type";
            $params['game_type'] = $gameType;
        }

        if ($teamId) {
            $sql .= " AND p.team_id = :team_id";
            $params['team_id'] = $teamId;
        }

        if ($search) {
            $sql .= " AND (p.nickname LIKE :search OR p.real_name LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getLastPlayerName(string $gameType): ?string
    {
        $stmt = $this->db->prepare("SELECT nickname FROM players WHERE game_type = :game_type ORDER BY nickname DESC LIMIT 1");
        $stmt->execute(['game_type' => $gameType]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Get player by ID
     */
    public function getPlayerById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, t.name as team_name 
            FROM players p 
            LEFT JOIN teams t ON p.team_id = t.id 
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get player by nickname and game type
     */
    public function getPlayerByNickname(string $nickname, string $gameType): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, t.name as team_name 
            FROM players p 
            LEFT JOIN teams t ON p.team_id = t.id 
            WHERE p.nickname = :nickname AND p.game_type = :game_type
        ");
        $stmt->execute(['nickname' => $nickname, 'game_type' => $gameType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get players by team ID
     */
    public function getPlayersByTeam(int $teamId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE team_id = :team_id ORDER BY nickname ASC");
        $stmt->execute(['team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save a new player or update existing one
     */
    public function savePlayer(array $data): int
    {
        // Check if player exists
        $existing = $this->getPlayerByNickname($data['nickname'], $data['game_type']);

        if ($existing) {
            // Update existing player
            $this->updatePlayer($existing['id'], $data);
            return $existing['id'];
        }

        // Insert new player
        $stmt = $this->db->prepare("
            INSERT INTO players (nickname, real_name, team_id, game_type, country, role, photo_url, birthdate, description, liquipedia_url)
            VALUES (:nickname, :real_name, :team_id, :game_type, :country, :role, :photo_url, :birthdate, :description, :liquipedia_url)
        ");

        $stmt->execute([
            'nickname' => $data['nickname'],
            'real_name' => $data['real_name'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'game_type' => $data['game_type'],
            'country' => $data['country'] ?? null,
            'role' => $data['role'] ?? null,
            'photo_url' => $data['photo_url'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'description' => $data['description'] ?? null,
            'liquipedia_url' => $data['liquipedia_url'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update player details
     */
    public function updatePlayer(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE players SET 
                real_name = :real_name,
                team_id = :team_id,
                country = :country,
                role = :role,
                photo_url = :photo_url,
                birthdate = :birthdate,
                description = :description,
                liquipedia_url = :liquipedia_url,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'real_name' => $data['real_name'] ?? null,
            'team_id' => $data['team_id'] ?? null,
            'country' => $data['country'] ?? null,
            'role' => $data['role'] ?? null,
            'photo_url' => $data['photo_url'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'description' => $data['description'] ?? null,
            'liquipedia_url' => $data['liquipedia_url'] ?? null
        ]);
    }

    /**
     * Update player's team
     */
    public function updatePlayerTeam(int $playerId, ?int $teamId): void
    {
        $stmt = $this->db->prepare("UPDATE players SET team_id = :team_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute(['id' => $playerId, 'team_id' => $teamId]);
    }

    /**
     * Delete a player
     */
    public function deletePlayer(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM players WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * Get player stats from matches
     */
    public function getPlayerMatchStats(string $nickname): array
    {
        $stmt = $this->db->prepare("
            SELECT ps.*, m.team1_name, m.team2_name, m.tournament_name, m.match_time
            FROM player_stats ps
            JOIN matches m ON ps.match_id = m.id
            WHERE ps.player_name = :nickname
            ORDER BY m.match_time DESC
            LIMIT 20
        ");
        $stmt->execute(['nickname' => $nickname]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
