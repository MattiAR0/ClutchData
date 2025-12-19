<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo para gestionar estadísticas avanzadas de jugadores
 */
class PlayerStatsModel
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Guarda estadísticas de jugadores para un partido
     */
    public function saveStats(int $matchId, array $players): bool
    {
        if (empty($players)) {
            return false;
        }

        // Eliminar stats anteriores de este partido
        $this->deleteByMatch($matchId);

        $sql = "INSERT INTO player_stats 
                (match_id, player_name, team_name, agent, kills, deaths, assists, 
                 acs, adr, kast, hs_percent, rating, first_bloods, first_deaths, clutches, data_source) 
                VALUES 
                (:match_id, :player_name, :team_name, :agent, :kills, :deaths, :assists,
                 :acs, :adr, :kast, :hs_percent, :rating, :first_bloods, :first_deaths, :clutches, :data_source)";

        $stmt = $this->db->prepare($sql);

        foreach ($players as $player) {
            try {
                $stmt->execute([
                    ':match_id' => $matchId,
                    ':player_name' => $player['name'] ?? 'Unknown',
                    ':team_name' => $player['team'] ?? null,
                    ':agent' => $player['agent'] ?? null,
                    ':kills' => $player['kills'] ?? 0,
                    ':deaths' => $player['deaths'] ?? 0,
                    ':assists' => $player['assists'] ?? 0,
                    ':acs' => $player['acs'] ?? null,
                    ':adr' => $player['adr'] ?? null,
                    ':kast' => $player['kast'] ?? null,
                    ':hs_percent' => $player['hs_percent'] ?? null,
                    ':rating' => $player['rating'] ?? null,
                    ':first_bloods' => $player['first_bloods'] ?? null,
                    ':first_deaths' => $player['first_deaths'] ?? null,
                    ':clutches' => $player['clutches'] ?? null,
                    ':data_source' => $player['data_source'] ?? 'liquipedia'
                ]);
            } catch (\PDOException $e) {
                error_log("PlayerStatsModel saveStats error: " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene estadísticas de un partido
     */
    public function getStatsByMatch(int $matchId): array
    {
        $sql = "SELECT * FROM player_stats WHERE match_id = :match_id ORDER BY team_name, kills DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':match_id' => $matchId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene estadísticas de un partido agrupadas por equipo
     */
    public function getStatsByMatchGrouped(int $matchId): array
    {
        $stats = $this->getStatsByMatch($matchId);

        $grouped = [
            'team1' => [],
            'team2' => []
        ];

        $teams = [];
        foreach ($stats as $player) {
            if (!in_array($player['team_name'], $teams)) {
                $teams[] = $player['team_name'];
            }
        }

        foreach ($stats as $player) {
            $teamKey = ($player['team_name'] === ($teams[0] ?? '')) ? 'team1' : 'team2';
            $grouped[$teamKey][] = $player;
        }

        return $grouped;
    }

    /**
     * Obtiene estadísticas de un jugador en todos sus partidos
     */
    public function getStatsByPlayer(string $playerName): array
    {
        $sql = "SELECT ps.*, m.team1_name, m.team2_name, m.tournament_name, m.match_time 
                FROM player_stats ps 
                JOIN matches m ON ps.match_id = m.id 
                WHERE ps.player_name LIKE :player_name 
                ORDER BY m.match_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':player_name' => "%$playerName%"]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene promedio de stats de un jugador
     */
    public function getPlayerAverages(string $playerName): array
    {
        $sql = "SELECT 
                    player_name,
                    COUNT(*) as matches_played,
                    AVG(kills) as avg_kills,
                    AVG(deaths) as avg_deaths,
                    AVG(assists) as avg_assists,
                    AVG(acs) as avg_acs,
                    AVG(adr) as avg_adr,
                    AVG(kast) as avg_kast,
                    AVG(hs_percent) as avg_hs,
                    SUM(first_bloods) as total_first_bloods,
                    SUM(first_deaths) as total_first_deaths
                FROM player_stats 
                WHERE player_name LIKE :player_name 
                GROUP BY player_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':player_name' => "%$playerName%"]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Elimina estadísticas de un partido
     */
    public function deleteByMatch(int $matchId): bool
    {
        $sql = "DELETE FROM player_stats WHERE match_id = :match_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':match_id' => $matchId]);
    }

    /**
     * Verifica si un partido tiene stats de VLR.gg
     */
    public function hasVlrStats(int $matchId): bool
    {
        $sql = "SELECT COUNT(*) FROM player_stats WHERE match_id = :match_id AND data_source = 'vlr'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':match_id' => $matchId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si un partido tiene stats de HLTV
     */
    public function hasHltvStats(int $matchId): bool
    {
        $sql = "SELECT COUNT(*) FROM player_stats WHERE match_id = :match_id AND data_source = 'hltv'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':match_id' => $matchId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene top jugadores por estadística
     */
    public function getTopPlayers(string $stat = 'acs', int $limit = 10): array
    {
        $allowedStats = ['acs', 'adr', 'kast', 'hs_percent', 'kills'];
        if (!in_array($stat, $allowedStats)) {
            $stat = 'acs';
        }

        $sql = "SELECT 
                    player_name,
                    team_name,
                    COUNT(*) as matches,
                    AVG($stat) as avg_stat,
                    MAX($stat) as max_stat
                FROM player_stats 
                WHERE $stat IS NOT NULL 
                GROUP BY player_name, team_name 
                HAVING matches >= 3
                ORDER BY avg_stat DESC 
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
