<?php

/**
 * Script de prueba para verificar la búsqueda de equipos con caracteres especiales
 * 
 * Ejecutar: php test_team_search.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\TeamModel;
use App\Classes\VlrScraper;

echo "=== Test de Búsqueda de Equipos ===\n\n";

// Test 1: TeamModel search variants
echo "1. Probando variantes de búsqueda en TeamModel...\n";
$teamModel = new TeamModel();

// Use reflection to test protected method
$reflection = new ReflectionClass($teamModel);
$method = $reflection->getMethod('getSearchVariants');
$method->setAccessible(true);

$testCases = ['kru', 'leviatan', 'furia', 'sentinels'];

foreach ($testCases as $search) {
    $variants = $method->invoke($teamModel, $search);
    echo "   '$search' → [" . implode(', ', $variants) . "]\n";
}

echo "\n2. Probando normalización en VlrScraper...\n";
$vlrScraper = new VlrScraper();

$vlrReflection = new ReflectionClass($vlrScraper);
$normalizeMethod = $vlrReflection->getMethod('normalizeForComparison');
$normalizeMethod->setAccessible(true);

$normalizeTests = [
    'KRÜ Esports' => 'kru',
    'LEVIATÁN' => 'leviatan',
    'FURIA Esports' => 'furia',
    'Sentinels' => 'sentinel',
];

foreach ($normalizeTests as $input => $expected) {
    $result = $normalizeMethod->invoke($vlrScraper, $input);
    $status = (str_contains($result, $expected)) ? '✓' : '✗';
    echo "   $status '$input' → '$result'\n";
}

echo "\n3. Probando búsqueda en base de datos...\n";
$searchTests = ['kru', 'leviatan', 'leviatán'];

foreach ($searchTests as $search) {
    $results = $teamModel->getAllTeams(null, null, $search, 5);
    $count = count($results);
    echo "   Búsqueda '$search': $count resultados";
    if ($count > 0) {
        echo " → " . implode(', ', array_column($results, 'name'));
    }
    echo "\n";
}

echo "\n=== Test completado ===\n";
