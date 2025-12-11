<?php

// public/index.php
// Punto de entrada único (Single Entry Point)

require_once __DIR__ . '/../vendor/autoload.php';

use App\Classes\Router;
use App\Controllers\MatchController;
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

// Definir Rutas
$router->get('/', [MatchController::class, 'index']);
$router->get('/valorant', fn() => (new MatchController())->index('valorant'));
$router->get('/lol', fn() => (new MatchController())->index('lol'));
$router->get('/cs2', fn() => (new MatchController())->index('cs2'));

$router->get('/scrape', [MatchController::class, 'scrape']); // Cambiar a GET para que funcione con enlaces simples
$router->get('/reset', [MatchController::class, 'reset']); // Nueva ruta reset

// Resolver Ruta Actual
$router->resolve();
