<?php
/**
 * Script para insertar datos de prueba mientras Liquipedia tiene rate limiting
 * Ejecutar con: php insert_test_data.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Models\MatchModel;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$model = new MatchModel();

// Limpiar datos existentes
echo "Limpiando datos existentes...\n";
$model->deleteAllMatches();

// Datos de prueba para verificar el sistema
$testMatches = [
    // Valorant - Americas
    ['team1' => 'Sentinels', 'team2' => 'Cloud9', 'tournament' => 'VCT Americas 2025', 'region' => 'Americas', 'time' => date('Y-m-d H:i:s', strtotime('+2 hours')), 'game_type' => 'valorant', 'match_status' => 'upcoming', 'match_importance' => 80],
    ['team1' => 'LOUD', 'team2' => 'NRG', 'tournament' => 'VCT Americas 2025', 'region' => 'Americas', 'time' => date('Y-m-d H:i:s', strtotime('+4 hours')), 'game_type' => 'valorant', 'match_status' => 'upcoming', 'match_importance' => 75],

    // Valorant - EMEA
    ['team1' => 'Fnatic', 'team2' => 'Team Vitality', 'tournament' => 'VCT EMEA 2025', 'region' => 'EMEA', 'time' => date('Y-m-d H:i:s', strtotime('+3 hours')), 'game_type' => 'valorant', 'match_status' => 'upcoming', 'match_importance' => 85],
    ['team1' => 'Team Liquid', 'team2' => 'NAVI', 'tournament' => 'VCT EMEA 2025', 'region' => 'EMEA', 'time' => date('Y-m-d H:i:s', strtotime('+5 hours')), 'game_type' => 'valorant', 'match_status' => 'upcoming', 'match_importance' => 80],

    // Valorant - Pacific
    ['team1' => 'DRX', 'team2' => 'Gen.G', 'tournament' => 'VCT Pacific 2025', 'region' => 'Pacific', 'time' => date('Y-m-d H:i:s', strtotime('+1 hours')), 'game_type' => 'valorant', 'match_status' => 'upcoming', 'match_importance' => 90],
    ['team1' => 'T1', 'team2' => 'Paper Rex', 'tournament' => 'VCT Pacific 2025', 'region' => 'Pacific', 'time' => date('Y-m-d H:i:s', strtotime('+6 hours')), 'game_type' => 'valorant', 'match_status' => 'upcoming', 'match_importance' => 85],

    // Valorant - Completed matches
    ['team1' => 'Sentinels', 'team2' => 'NRG', 'tournament' => 'VCT Americas 2025', 'region' => 'Americas', 'time' => date('Y-m-d H:i:s', strtotime('-1 day')), 'game_type' => 'valorant', 'team1_score' => 2, 'team2_score' => 1, 'match_status' => 'completed', 'match_importance' => 70],

    // LoL - EMEA
    ['team1' => 'G2 Esports', 'team2' => 'Fnatic', 'tournament' => 'LEC 2026 Versus', 'region' => 'EMEA', 'time' => date('Y-m-d H:i:s', strtotime('+2 hours')), 'game_type' => 'lol', 'match_status' => 'upcoming', 'match_importance' => 90],
    ['team1' => 'MAD Lions', 'team2' => 'Rogue', 'tournament' => 'LEC 2026 Versus', 'region' => 'EMEA', 'time' => date('Y-m-d H:i:s', strtotime('+4 hours')), 'game_type' => 'lol', 'match_status' => 'upcoming', 'match_importance' => 75],

    // LoL - Pacific
    ['team1' => 'T1', 'team2' => 'Gen.G', 'tournament' => 'LCK 2025', 'region' => 'Pacific', 'time' => date('Y-m-d H:i:s', strtotime('+1 hours')), 'game_type' => 'lol', 'match_status' => 'upcoming', 'match_importance' => 95],
    ['team1' => 'JD Gaming', 'team2' => 'Top Esports', 'tournament' => 'LPL 2025', 'region' => 'Pacific', 'time' => date('Y-m-d H:i:s', strtotime('+3 hours')), 'game_type' => 'lol', 'match_status' => 'upcoming', 'match_importance' => 90],

    // CS2 - EMEA
    ['team1' => 'NAVI', 'team2' => 'G2 Esports', 'tournament' => 'BLAST Premier 2025', 'region' => 'International', 'time' => date('Y-m-d H:i:s', strtotime('+2 hours')), 'game_type' => 'cs2', 'match_status' => 'upcoming', 'match_importance' => 95],
    ['team1' => 'Vitality', 'team2' => 'FaZe Clan', 'tournament' => 'BLAST Premier 2025', 'region' => 'International', 'time' => date('Y-m-d H:i:s', strtotime('+4 hours')), 'game_type' => 'cs2', 'match_status' => 'upcoming', 'match_importance' => 90],
    ['team1' => 'Cloud9', 'team2' => 'Liquid', 'tournament' => 'ESL Pro League', 'region' => 'Americas', 'time' => date('Y-m-d H:i:s', strtotime('+5 hours')), 'game_type' => 'cs2', 'match_status' => 'upcoming', 'match_importance' => 80],
];

echo "Insertando " . count($testMatches) . " partidos de prueba...\n";

$model->saveMatches($testMatches);

echo "✅ Datos de prueba insertados correctamente!\n";
echo "\nPuedes verificar en: http://localhost/ClutchData/\n";
echo "Y en equipos: http://localhost/ClutchData/teams\n";
