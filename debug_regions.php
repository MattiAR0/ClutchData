<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);

echo "=== Distribución de Regiones en Matches ===\n";
$stmt = $db->query('SELECT match_region, COUNT(*) as cnt FROM matches GROUP BY match_region ORDER BY cnt DESC');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Torneos y sus Regiones (muestra) ===\n";
$stmt = $db->query('SELECT DISTINCT tournament_name, match_region FROM matches ORDER BY tournament_name LIMIT 25');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
