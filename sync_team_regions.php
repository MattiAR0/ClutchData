<?php

/**
 * Script para sincronizar las regiones de equipos basándose en los partidos
 * 
 * Reglas:
 * 1. Un equipo puede tener diferentes regiones según el juego
 * 2. Los partidos internacionales no afectan la región del equipo
 * 3. Se usa la región más frecuente de los partidos regionales
 */

require __DIR__ . '/vendor/autoload.php';

use App\Classes\Database;

$db = Database::getInstance()->getConnection();

$output = [];
$output[] = "=== Sincronizacion de Regiones de Equipos ===";
$output[] = "Fecha: " . date('Y-m-d H:i:s');
$output[] = "";

// Paso 1: Obtener todos los equipos únicos de matches con su región más frecuente
$sql = "
    SELECT 
        team_name,
        game_type,
        match_region,
        COUNT(*) as match_count
    FROM (
        SELECT team1_name as team_name, game_type, match_region
        FROM matches 
        WHERE match_region IS NOT NULL 
          AND match_region != 'Other'
          AND match_region NOT IN ('International', 'World', 'Global', 'Worldwide')
          AND match_region IN ('Americas', 'EMEA', 'Pacific')
        
        UNION ALL
        
        SELECT team2_name as team_name, game_type, match_region
        FROM matches 
        WHERE match_region IS NOT NULL 
          AND match_region != 'Other'
          AND match_region NOT IN ('International', 'World', 'Global', 'Worldwide')
          AND match_region IN ('Americas', 'EMEA', 'Pacific')
    ) as all_team_matches
    WHERE team_name IS NOT NULL 
      AND team_name != '' 
      AND team_name != 'TBD' 
      AND team_name != 'TBA'
    GROUP BY team_name, game_type, match_region
    ORDER BY team_name, game_type, match_count DESC
";

$stmt = $db->query($sql);
$regionData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$teamRegions = [];
foreach ($regionData as $row) {
    $key = $row['team_name'] . '|' . $row['game_type'];
    if (!isset($teamRegions[$key])) {
        $teamRegions[$key] = [
            'team_name' => $row['team_name'],
            'game_type' => $row['game_type'],
            'region' => $row['match_region'],
            'match_count' => $row['match_count']
        ];
    }
}

$output[] = "Encontrados " . count($teamRegions) . " equipos con region determinable";
$output[] = "";

// Paso 2: Actualizar la tabla teams
$updated = 0;
$created = 0;
$skipped = 0;

$updateStmt = $db->prepare("
    UPDATE teams 
    SET region = :region, updated_at = CURRENT_TIMESTAMP
    WHERE name = :name AND game_type = :game_type AND (region = 'Other' OR region IS NULL)
");

$checkStmt = $db->prepare("
    SELECT id, region FROM teams WHERE name = :name AND game_type = :game_type
");

$insertStmt = $db->prepare("
    INSERT INTO teams (name, game_type, region) 
    VALUES (:name, :game_type, :region)
");

foreach ($teamRegions as $data) {
    $checkStmt->execute([
        'name' => $data['team_name'],
        'game_type' => $data['game_type']
    ]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['region'] === 'Other' || $existing['region'] === null) {
            $updateStmt->execute([
                'region' => $data['region'],
                'name' => $data['team_name'],
                'game_type' => $data['game_type']
            ]);

            if ($updateStmt->rowCount() > 0) {
                $output[] = "ACTUALIZADO: {$data['team_name']} ({$data['game_type']}) -> {$data['region']} ({$data['match_count']} partidos)";
                $updated++;
            }
        } else {
            $skipped++;
        }
    } else {
        $insertStmt->execute([
            'name' => $data['team_name'],
            'game_type' => $data['game_type'],
            'region' => $data['region']
        ]);
        $output[] = "CREADO: {$data['team_name']} ({$data['game_type']}) -> {$data['region']}";
        $created++;
    }
}

$output[] = "";
$output[] = "=== Resumen ===";
$output[] = "Equipos actualizados: $updated";
$output[] = "Equipos creados: $created";
$output[] = "Equipos sin cambios (ya tenian region): $skipped";

$output[] = "";
$output[] = "=== Distribucion Final de Regiones ===";
$stats = $db->query("SELECT region, COUNT(*) as count FROM teams GROUP BY region ORDER BY count DESC");
foreach ($stats->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $output[] = "  {$row['region']}: {$row['count']} equipos";
}

$output[] = "";
$output[] = "=== Equipos sin region determinable ===";
$otherTeams = $db->query("
    SELECT name, game_type FROM teams 
    WHERE region = 'Other' OR region IS NULL
    ORDER BY name
    LIMIT 30
");
$otherList = $otherTeams->fetchAll(PDO::FETCH_ASSOC);
if (empty($otherList)) {
    $output[] = "  Todos los equipos tienen region asignada!";
} else {
    foreach ($otherList as $team) {
        $output[] = "  - {$team['name']} ({$team['game_type']})";
    }
    $countOther = $db->query("SELECT COUNT(*) FROM teams WHERE region = 'Other' OR region IS NULL")->fetchColumn();
    if ($countOther > 30) {
        $output[] = "  ... y " . ($countOther - 30) . " mas";
    }
}

// Guardar reporte
$report = implode("\n", $output);
file_put_contents(__DIR__ . '/sync_regions_report.txt', $report);
echo $report;
echo "\n\nReporte guardado en sync_regions_report.txt\n";
