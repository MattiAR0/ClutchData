<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM matches LIKE 'match_details'");
    $column = $stmt->fetch();

    if (!$column) {
        // Add match_details column
        $sql = "ALTER TABLE matches ADD COLUMN match_details JSON DEFAULT NULL AFTER match_url";
        $pdo->exec($sql);
        echo "Migration successful: match_details column added.\n";
    } else {
        echo "Migration skipped: match_details column already exists.\n";
    }

} catch (\PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
