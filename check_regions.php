<?php

/**
 * Script para verificar el estado de las regiones de equipos
 */
require __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;

$output = [];
$db = Database::getInstance()->getConnection();

$output[] = "=== Estado de Regiones en la Tabla TEAMS ===";

// Contar equipos por región
$result = $db->query('SELECT region, COUNT(*) as count FROM teams GROUP BY region ORDER BY count DESC');
$output[] = "Distribucion por region:";
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $output[] = "  - {$row['region']}: {$row['count']} equipos";
}

$output[] = "";
$output[] = "=== Estado de Regiones en la Tabla MATCHES ===";

// Contar partidos por región
$result = $db->query('SELECT match_region, COUNT(*) as count FROM matches GROUP BY match_region ORDER BY count DESC');
$output[] = "Distribucion por region:";
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $region = $row['match_region'] ?: 'NULL';
    $output[] = "  - {$region}: {$row['count']} partidos";
}

$output[] = "";
$output[] = "=== Muestra de Equipos con Region 'Other' ===";

$result = $db->query("SELECT name, game_type, region FROM teams WHERE region = 'Other' LIMIT 20");
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $output[] = "  - {$row['name']} ({$row['game_type']})";
}

$output[] = "";
$output[] = "=== Equipos en tabla Teams vs Matches ===";

// Verificar si los equipos en matches tienen la región correcta basada en match_region
$result = $db->query("
    SELECT t.name, t.game_type, t.region as team_region, 
           (SELECT DISTINCT m.match_region FROM matches m WHERE (m.team1_name = t.name OR m.team2_name = t.name) AND m.game_type = t.game_type AND m.match_region IS NOT NULL AND m.match_region != 'Other' LIMIT 1) as match_region
    FROM teams t 
    WHERE t.region = 'Other'
    LIMIT 20
");

$output[] = "Equipos 'Other' que podrian tener mejor region desde matches:";
foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $matchRegion = $row['match_region'] ?: 'ninguna';
    $output[] = "  - {$row['name']} ({$row['game_type']}): team_region={$row['team_region']}, match_region={$matchRegion}";
}

// Guardar en archivo
file_put_contents(__DIR__ . '/regions_report.txt', implode("\n", $output));
echo "Reporte guardado en regions_report.txt\n";
