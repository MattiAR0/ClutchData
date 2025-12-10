<?php

// public/index.php
// Punto de entrada único

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\ValorantScraper;
use App\Classes\LolScraper;
use App\Classes\Cs2Scraper;
use App\Models\MatchModel;
use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
// En algunos entornos de desarrollo Windows/Wamp, putenv puede ser necesario
try {
    $dotenv->load();
} catch (Exception $e) {
    // Si falla (ej. archivo no existe en primera ejecución), continuamos.
    // La clase Database maneja valores por defecto.
}

// Enrutamiento Básico (Muy simple para prueba)
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Limpiar URI de query params
$path = parse_url($uri, PHP_URL_PATH);

// Controller "Inline" para simplificar el skeleton
// Eliminamos la comprobación estricta de path para que funcione en subdirectorios
// if ($path === '/' || str_contains($path, 'index.php')) { 
$matches = [];
$error = null;

// Si se forzó el scraping vía parámetro GET ?scrape=1
if (isset($_GET['scrape']) && $_GET['scrape'] === '1') {
    try {
        $model = new MatchModel();

        // Instanciar Scrapers
        $scrapers = [
            new ValorantScraper(),
            new LolScraper(),
            new Cs2Scraper()
        ];

        foreach ($scrapers as $scraper) {
            $data = $scraper->scrapeMatches();
            $model->saveMatches($data);
        }

        $message = "Scraping completado exitosamente.";
    } catch (Exception $e) {
        $error = "Error durante el scraping: " . $e->getMessage();
    }
}

// Obtener datos para la vista
try {
    // Intentamos conectar a DB para mostrar datos si existe la tabla
    $model = new MatchModel();
    $matches = $model->getAllMatches();
} catch (Exception $e) {
    $error = "No se pudo conectar a la base de datos o leer partidos: " . $e->getMessage();
}

// Cargar Vista
include __DIR__ . '/../views/home.php';
exit;
//}

// 404
http_response_code(404);
echo "404 Not Found";
