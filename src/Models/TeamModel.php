<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Database;
use PDO;
use Exception;

/**
 * Model for managing teams in the database
 */
class TeamModel
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
     * Get all teams, optionally filtered by game type
     */
    public function getAllTeams(?string $gameType = null, ?string $region = null): array
    {
        $sql = "SELECT * FROM teams WHERE 1=1";
        $params = [];

        if ($gameType && $gameType !== 'all') {
            $sql .= " AND game_type = :game_type";
            $params['game_type'] = $gameType;
        }

        if ($region && $region !== 'all') {
            $sql .= " AND region = :region";
            $params['region'] = $region;
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get team by ID
     */
    public function getTeamById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get team by name and game type
     */
    public function getTeamByNameAndGame(string $name, string $gameType): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM teams WHERE name = :name AND game_type = :game_type");
        $stmt->execute(['name' => $name, 'game_type' => $gameType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Save a new team or update existing one
     */
    public function saveTeam(array $data): int
    {
        // Check if team exists
        $existing = $this->getTeamByNameAndGame($data['name'], $data['game_type']);

        if ($existing) {
            // Update existing team
            $this->updateTeam($existing['id'], $data);
            return $existing['id'];
        }

        // Insert new team
        $stmt = $this->db->prepare("
            INSERT INTO teams (name, game_type, region, country, logo_url, description, liquipedia_url)
            VALUES (:name, :game_type, :region, :country, :logo_url, :description, :liquipedia_url)
        ");

        $stmt->execute([
            'name' => $data['name'],
            'game_type' => $data['game_type'],
            'region' => $data['region'] ?? 'Other',
            'country' => $data['country'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'description' => $data['description'] ?? null,
            'liquipedia_url' => $data['liquipedia_url'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update team details
     */
    public function updateTeam(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE teams SET 
                region = :region,
                country = :country,
                logo_url = :logo_url,
                description = :description,
                liquipedia_url = :liquipedia_url,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'region' => $data['region'] ?? 'Other',
            'country' => $data['country'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'description' => $data['description'] ?? null,
            'liquipedia_url' => $data['liquipedia_url'] ?? null
        ]);
    }

    /**
     * Get teams that appear in matches
     */
    public function getTeamsFromMatches(?string $gameType = null): array
    {
        $sql = "
            SELECT DISTINCT team1_name as name, game_type FROM matches
            UNION
            SELECT DISTINCT team2_name as name, game_type FROM matches
        ";

        if ($gameType && $gameType !== 'all') {
            $sql = "
                SELECT DISTINCT team1_name as name, game_type FROM matches WHERE game_type = :game_type
                UNION
                SELECT DISTINCT team2_name as name, game_type FROM matches WHERE game_type = :game_type
            ";
        }

        $sql = "SELECT DISTINCT name, game_type FROM ($sql) as teams ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        if ($gameType && $gameType !== 'all') {
            $stmt->execute(['game_type' => $gameType]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete a team
     */
    public function deleteTeam(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM teams WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * Get matches for a team
     */
    public function getTeamMatches(string $teamName, string $gameType, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM matches 
            WHERE (team1_name = :team1 OR team2_name = :team2) 
              AND game_type = :game_type
            ORDER BY match_time DESC
            LIMIT :limit
        ");

        $stmt->bindValue('team1', $teamName, PDO::PARAM_STR);
        $stmt->bindValue('team2', $teamName, PDO::PARAM_STR);
        $stmt->bindValue('game_type', $gameType, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
