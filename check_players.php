<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Classes\Database;

$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
} catch (Exception $e) {
}

$db = Database::getInstance()->getConnection();

echo "=== DIAGNÓSTICO DE JUGADORES ===\n\n";

// Total jugadores
echo "Jugadores totales en tabla 'players': " . $db->query("SELECT COUNT(*) FROM players")->fetchColumn() . "\n\n";

// Por juego
echo "Por juego:\n";
$stmt = $db->query("SELECT game_type, COUNT(*) as c FROM players GROUP BY game_type");
foreach ($stmt->fetchAll() as $r) {
    echo "  - {$r['game_type']}: {$r['c']}\n";
}

echo "\n=== EQUIPOS ===\n";
echo "Equipos totales: " . $db->query("SELECT COUNT(*) FROM teams")->fetchColumn() . "\n";

echo "\n=== JUGADORES ÚNICOS EN PLAYER_STATS ===\n";
$uniqueStats = $db->query("SELECT COUNT(DISTINCT player_name) FROM player_stats WHERE player_name IS NOT NULL AND player_name != ''")->fetchColumn();
echo "Jugadores únicos en player_stats: $uniqueStats\n";

echo "\n=== JUGADORES EN STATS QUE NO ESTÁN EN PLAYERS ===\n";
$missing = $db->query("
    SELECT DISTINCT ps.player_name, m.game_type
    FROM player_stats ps
    JOIN matches m ON ps.match_id = m.id
    WHERE ps.player_name IS NOT NULL 
      AND ps.player_name != ''
      AND ps.player_name != 'TBD'
      AND NOT EXISTS (
          SELECT 1 FROM players p 
          WHERE p.nickname = ps.player_name AND p.game_type = m.game_type
      )
    LIMIT 20
")->fetchAll();

echo "Faltan " . count($missing) . " jugadores (mostrando max 20):\n";
foreach ($missing as $m) {
    echo "  - {$m['player_name']} ({$m['game_type']})\n";
}

echo "\nEjecuta: php sync_all.php --players-only  para sincronizar\n";
