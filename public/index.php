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

// Router Instantiation
$router = new Router();

// Definir Rutas
$router->get('/', [MatchController::class, 'index']);
$router->get('/scrape', [MatchController::class, 'scrape']); // Cambiar a GET para que funcione con enlaces simples
$router->get('/reset', [MatchController::class, 'reset']); // Nueva ruta reset

// Resolver Ruta Actual
$router->resolve();
