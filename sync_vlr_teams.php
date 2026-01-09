<?php

/**
 * Script para importar equipos tier 2 desde VLR.gg Rankings
 * 
 * Uso:
 *   php sync_vlr_teams.php                    # Importar de todas las regiones
 *   php sync_vlr_teams.php --region=la-s      # Solo Latinoamérica Sur
 *   php sync_vlr_teams.php --region=brazil    # Solo Brasil
 *   php sync_vlr_teams.php --list-regions     # Listar regiones disponibles
 */

require __DIR__ . '/vendor/autoload.php';

use App\Classes\VlrScraper;
use App\Models\TeamModel;

echo "=== Importador de Equipos VLR.gg ===\n\n";

// Parse command line arguments
$options = getopt('', ['region:', 'list-regions', 'limit:', 'help']);

if (isset($options['help'])) {
    echo "Uso: php sync_vlr_teams.php [opciones]\n\n";
    echo "Opciones:\n";
    echo "  --region=REGION   Importar solo de una región específica\n";
    echo "  --list-regions    Listar regiones disponibles\n";
    echo "  --limit=N         Limitar a N equipos por región\n";
    echo "  --help            Mostrar esta ayuda\n\n";
    exit(0);
}

$availableRegions = [
    'europe'        => 'Europa (VCT EMEA)',
    'north-america' => 'Norteamérica',
    'brazil'        => 'Brasil',
    'asia-pacific'  => 'Asia-Pacífico (SEA, India)',
    'korea'         => 'Corea',
    'china'         => 'China',
    'japan'         => 'Japón',
    'la-s'          => 'Latinoamérica Sur (Argentina, Chile)',
    'la-n'          => 'Latinoamérica Norte (México)',
    'oceania'       => 'Oceanía',
    'mena'          => 'MENA (Medio Oriente, Norte de África)',
    'gc'            => 'Game Changers',
];

if (isset($options['list-regions'])) {
    echo "Regiones disponibles:\n";
    foreach ($availableRegions as $code => $name) {
        echo "  --region=$code\t$name\n";
    }
    exit(0);
}

$vlrScraper = new VlrScraper();
$teamModel = new TeamModel();
$limit = isset($options['limit']) ? (int)$options['limit'] : null;

// Determine which regions to scrape
$regionsToScrape = [];
if (isset($options['region'])) {
    $region = $options['region'];
    if (!isset($availableRegions[$region])) {
        echo "❌ Región '$region' no válida.\n";
        echo "Use --list-regions para ver regiones disponibles.\n";
        exit(1);
    }
    $regionsToScrape = [$region];
    echo "📍 Región seleccionada: {$availableRegions[$region]}\n\n";
} else {
    $regionsToScrape = array_keys($availableRegions);
    echo "📍 Importando de TODAS las regiones...\n\n";
}

$totalImported = 0;
$totalUpdated = 0;
$totalSkipped = 0;

foreach ($regionsToScrape as $region) {
    echo "🔄 Scrapeando {$availableRegions[$region]}...\n";

    try {
        $teams = $vlrScraper->scrapeRankings($region);

        if ($limit) {
            $teams = array_slice($teams, 0, $limit);
        }

        $regionImported = 0;
        $regionUpdated = 0;

        foreach ($teams as $team) {
            // Check if team exists
            $existing = $teamModel->getTeamByNameAndGame($team['name'], 'valorant');

            if ($existing) {
                // Update if missing data
                if (empty($existing['country']) && !empty($team['country'])) {
                    $teamModel->updateTeam($existing['id'], [
                        'region' => $team['region'],
                        'country' => $team['country'],
                        'logo_url' => $existing['logo_url'],
                        'description' => $existing['description'],
                        'liquipedia_url' => $existing['liquipedia_url'],
                    ]);
                    $regionUpdated++;
                    $totalUpdated++;
                } else {
                    $totalSkipped++;
                }
            } else {
                // Insert new team
                $teamModel->saveTeam([
                    'name' => $team['name'],
                    'game_type' => 'valorant',
                    'region' => $team['region'],
                    'country' => $team['country'],
                    'logo_url' => null,
                    'description' => null,
                    'liquipedia_url' => null,
                ]);
                $regionImported++;
                $totalImported++;
            }
        }

        echo "   ✓ Encontrados: " . count($teams) . " equipos";
        if ($regionImported > 0) echo " | Nuevos: $regionImported";
        if ($regionUpdated > 0) echo " | Actualizados: $regionUpdated";
        echo "\n";
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }

    // Rate limit between regions
    if (count($regionsToScrape) > 1) {
        sleep(2);
    }
}

echo "\n=== Resumen ===\n";
echo "✅ Equipos nuevos importados: $totalImported\n";
echo "📝 Equipos actualizados: $totalUpdated\n";
echo "⏭️  Equipos sin cambios: $totalSkipped\n";
echo "\nListo! Los equipos tier 2 ahora deberían aparecer en las búsquedas.\n";
