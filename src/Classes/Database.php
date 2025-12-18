<?php

declare(strict_types=1);

namespace App\Classes;

use PDO;
use PDOException;
use Dotenv\Dotenv;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        // Cargar variables de entorno si no se han cargado (opcional, normalmente se hace en index.php)
        // $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        // $dotenv->safeLoad();

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db = $_ENV['DB_NAME'] ?? 'clutchdata_db';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // En producción, loguear el error y mostrar mensaje genérico
            throw new PDOException($e->getMessage(), (int) $e->getCode());
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    // Prevenir clonación y unserialize
    private function __clone()
    {
    }
    public function __wakeup()
    {
    }
}
