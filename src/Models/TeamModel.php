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
     * Normalize text for search (remove accents)
     * Used to create search variants for flexible matching
     */
    protected function normalizeForSearch(string $text): string
    {
        $accentMap = [
            'á' => 'a',
            'à' => 'a',
            'ä' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ë' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'ï' => 'i',
            'î' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ö' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'ü' => 'u',
            'û' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            'Á' => 'A',
            'À' => 'A',
            'Ä' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'É' => 'E',
            'È' => 'E',
            'Ë' => 'E',
            'Ê' => 'E',
            'Í' => 'I',
            'Ì' => 'I',
            'Ï' => 'I',
            'Î' => 'I',
            'Ó' => 'O',
            'Ò' => 'O',
            'Ö' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ú' => 'U',
            'Ù' => 'U',
            'Ü' => 'U',
            'Û' => 'U',
            'Ñ' => 'N',
            'Ç' => 'C',
        ];

        return strtr($text, $accentMap);
    }

    /**
     * Get search variants for a term (original + normalized)
     */
    protected function getSearchVariants(string $search): array
    {
        $variants = [$search];
        $normalized = $this->normalizeForSearch($search);

        if ($normalized !== $search) {
            $variants[] = $normalized;
        }

        // Also add common team name mappings
        $mappings = [
            'kru' => ['krü', 'kru esports', 'krü esports'],
            'leviatan' => ['leviatán', 'lev'],
            'furia' => ['furia esports', 'fur'],
            'sentinels' => ['sen'],
            'fnatic' => ['fnc'],
            'g2' => ['g2 esports'],
        ];

        $searchLower = strtolower($search);
        if (isset($mappings[$searchLower])) {
            $variants = array_merge($variants, $mappings[$searchLower]);
        }

        // Check reverse mappings
        foreach ($mappings as $main => $aliases) {
            if (in_array($searchLower, $aliases)) {
                $variants[] = $main;
            }
        }

        return array_unique($variants);
    }

    /**
     * Get all teams, optionally filtered by game type
     */
    public function getAllTeams(?string $gameType = null, ?string $region = null, ?string $search = null, int $limit = 1000, int $offset = 0): array
    {
        $sql = "SELECT * FROM teams WHERE 1=1";
        $params = [];

        if ($gameType && $gameType !== 'all') {
            $sql .= " AND game_type = :game_type";
            $params['game_type'] = $gameType;
        }

        if ($region && $region !== 'all') {
            if ($region === 'Other') {
                $sql .= " AND region NOT IN ('Americas', 'EMEA', 'Pacific')";
            } else {
                $sql .= " AND region = :region";
                $params['region'] = $region;
            }
        }

        if ($search) {
            // Get search variants (original + normalized + known aliases)
            $variants = $this->getSearchVariants($search);
            $searchConditions = [];

            foreach ($variants as $i => $variant) {
                $paramName = "search_$i";
                $searchConditions[] = "name LIKE :$paramName";
                $params[$paramName] = '%' . $variant . '%';
            }

            $sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
        }

        $sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalCount(?string $gameType = null, ?string $region = null, ?string $search = null): int
    {
        $sql = "SELECT COUNT(*) FROM teams WHERE 1=1";
        $params = [];

        if ($gameType && $gameType !== 'all') {
            $sql .= " AND game_type = :game_type";
            $params['game_type'] = $gameType;
        }

        if ($region && $region !== 'all') {
            if ($region === 'Other') {
                $sql .= " AND region NOT IN ('Americas', 'EMEA', 'Pacific')";
            } else {
                $sql .= " AND region = :region";
                $params['region'] = $region;
            }
        }

        if ($search) {
            // Get search variants (original + normalized + known aliases)
            $variants = $this->getSearchVariants($search);
            $searchConditions = [];

            foreach ($variants as $i => $variant) {
                $paramName = "search_$i";
                $searchConditions[] = "name LIKE :$paramName";
                $params[$paramName] = '%' . $variant . '%';
            }

            $sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getLastTeamName(string $gameType): ?string
    {
        $stmt = $this->db->prepare("SELECT name FROM teams WHERE game_type = :game_type ORDER BY name DESC LIMIT 1");
        $stmt->execute(['game_type' => $gameType]);
        return $stmt->fetchColumn() ?: null;
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
     * Get teams that appear in matches (includes region from match data)
     */
    public function getTeamsFromMatches(?string $gameType = null): array
    {
        // Use a subquery to get all team-game-region combinations, then group to avoid duplicates
        // When a team appears in multiple regions, prefer the most common one (using MAX as simple tie-breaker)
        $baseQuery = "
            SELECT team1_name as name, game_type, match_region as region FROM matches
            UNION ALL
            SELECT team2_name as name, game_type, match_region as region FROM matches
        ";

        if ($gameType && $gameType !== 'all') {
            $baseQuery = "
                SELECT team1_name as name, game_type, match_region as region FROM matches WHERE game_type = :game_type1
                UNION ALL
                SELECT team2_name as name, game_type, match_region as region FROM matches WHERE game_type = :game_type2
            ";
        }

        // Group by team name and game type, pick the first region alphabetically (or most frequent if needed)
        $sql = "
            SELECT name, game_type, MAX(region) as region 
            FROM ($baseQuery) as all_teams 
            WHERE name IS NOT NULL AND name != '' AND name != 'TBD' AND name != 'TBA'
            GROUP BY name, game_type 
            ORDER BY name ASC
        ";

        $stmt = $this->db->prepare($sql);
        if ($gameType && $gameType !== 'all') {
            $stmt->execute(['game_type1' => $gameType, 'game_type2' => $gameType]);
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

    public function hasAnyTeams(): bool
    {
        $stmt = $this->db->query("SELECT 1 FROM teams LIMIT 1");
        return (bool) $stmt->fetch();
    }
}
