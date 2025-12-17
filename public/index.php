<?php

// public/index.php
// Punto de entrada único (Single Entry Point)

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\Router;
use App\Controllers\MatchController;
use App\Controllers\TeamController;
use App\Controllers\PlayerController;
use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
try {
    $dotenv->load();
} catch (Exception $e) {
    // Continuamos si falla .env (ej. producción real env vars)
}

// Iniciar Sesión para Flash Messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Router Instantiation
$router = new Router();

// Definir Rutas - Matches
$router->get('/', [MatchController::class, 'index']);
$router->get('/valorant', fn() => (new MatchController())->index('valorant'));
$router->get('/lol', fn() => (new MatchController())->index('lol'));
$router->get('/cs2', fn() => (new MatchController())->index('cs2'));

$router->get('/scrape', [MatchController::class, 'scrape']); // Sync data
$router->get('/reset', [MatchController::class, 'reset']); // Reset database
$router->get('/match', [MatchController::class, 'show']); // Match details

// Definir Rutas - Teams
$router->get('/teams', [TeamController::class, 'index']);
$router->get('/team', [TeamController::class, 'show']);

// Definir Rutas - Players
$router->get('/player', [PlayerController::class, 'show']);

// Resolver Ruta Actual
$router->resolve();

