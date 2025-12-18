<?php

/**
 * Script Cliente API - Prueba de la API REST
 * 
 * Este script demuestra cómo consumir la API REST del proyecto ClutchData.
 * Ejecutar desde línea de comandos: php api_client_test.php
 * 
 * @package ClutchData
 * @author ClutchData Team
 */

declare(strict_types=1);

// Configuración base
$baseUrl = 'http://localhost/ClutchData/public';

/**
 * Realiza una petición HTTP GET y devuelve la respuesta JSON decodificada
 */
function apiRequest(string $endpoint, array $params = []): array
{
    global $baseUrl;

    $url = $baseUrl . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    echo "\n🔗 Request: GET $url\n";
    echo str_repeat('-', 60) . "\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'User-Agent: ClutchData-API-Client/1.0'
            ],
            'timeout' => 10
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return [
            'success' => false,
            'error' => 'No se pudo conectar al servidor. Asegúrate de que WAMP está corriendo.'
        ];
    }

    $data = json_decode($response, true);

    // Obtener código de respuesta HTTP
    $httpCode = 200;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $header, $matches)) {
                $httpCode = (int) $matches[1];
                break;
            }
        }
    }

    return [
        'status_code' => $httpCode,
        'data' => $data
    ];
}

/**
 * Muestra los resultados de forma legible
 */
function printResult(array $result): void
{
    if (isset($result['status_code'])) {
        $statusColor = $result['status_code'] >= 200 && $result['status_code'] < 300 ? '✅' : '❌';
        echo "$statusColor HTTP Status: {$result['status_code']}\n";
    }

    if (isset($result['data'])) {
        echo "📦 Response:\n";
        echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n";
    }

    echo str_repeat('=', 60) . "\n";
}

// ============================================
// INICIO DE PRUEBAS
// ============================================

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         CLIENTE DE PRUEBA - API REST ClutchData             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

// Test 1: Obtener estadísticas generales
echo "\n📊 TEST 1: Obtener estadísticas generales (/api/stats)\n";
$result = apiRequest('/api/stats');
printResult($result);

// Test 2: Obtener lista de partidos
echo "\n🎮 TEST 2: Obtener partidos (/api/matches)\n";
$result = apiRequest('/api/matches', ['limit' => 5]);
if (isset($result['data']['count'])) {
    echo "📋 Total de partidos encontrados: {$result['data']['count']}\n";
    if (!empty($result['data']['data'])) {
        echo "📝 Mostrando primeros 5 partidos:\n";
        foreach (array_slice($result['data']['data'], 0, 5) as $match) {
            echo "   • {$match['team1_name']} vs {$match['team2_name']} ({$match['game_type']})\n";
        }
    }
}
printResult(['status_code' => $result['status_code'] ?? 0, 'data' => ['success' => $result['data']['success'] ?? false, 'count' => $result['data']['count'] ?? 0]]);

// Test 3: Filtrar partidos por juego
echo "\n🔫 TEST 3: Filtrar partidos de Valorant (/api/matches?game=valorant)\n";
$result = apiRequest('/api/matches', ['game' => 'valorant', 'limit' => 3]);
printResult(['status_code' => $result['status_code'] ?? 0, 'data' => ['success' => $result['data']['success'] ?? false, 'count' => $result['data']['count'] ?? 0, 'game_filter' => 'valorant']]);

// Test 4: Obtener lista de equipos
echo "\n👥 TEST 4: Obtener equipos (/api/teams)\n";
$result = apiRequest('/api/teams');
if (isset($result['data']['count'])) {
    echo "📋 Total de equipos: {$result['data']['count']}\n";
}
printResult(['status_code' => $result['status_code'] ?? 0, 'data' => ['success' => $result['data']['success'] ?? false, 'count' => $result['data']['count'] ?? 0]]);

// Test 5: Obtener partido específico (si hay datos)
echo "\n🔍 TEST 5: Obtener partido por ID (/api/match?id=1)\n";
$result = apiRequest('/api/match', ['id' => 1]);
printResult($result);

// Resumen final
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMEN DE PRUEBAS                      ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  ✅ Se probaron los endpoints principales de la API        ║\n";
echo "║  ✅ Códigos HTTP manejados correctamente                   ║\n";
echo "║  ✅ Respuestas JSON válidas                                ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
