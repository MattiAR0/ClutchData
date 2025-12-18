<?php

declare(strict_types=1);

namespace App\Classes;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

/**
 * Logger centralizado usando Monolog
 * Wrapper para facilitar el logging en toda la aplicación
 * 
 * @package App\Classes
 */
class Logger
{
    private static ?Logger $instance = null;
    private MonologLogger $logger;
    private static string $logPath = __DIR__ . '/../../logs/';

    private function __construct(string $channel = 'clutchdata')
    {
        $this->logger = new MonologLogger($channel);

        // Crear directorio de logs si no existe
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }

        // Handler para archivo diario (rotación automática)
        $this->logger->pushHandler(
            new RotatingFileHandler(
                self::$logPath . 'app.log',
                30, // Mantener 30 días de logs
                Level::Debug
            )
        );

        // Handler específico para errores
        $this->logger->pushHandler(
            new StreamHandler(
                self::$logPath . 'error.log',
                Level::Error
            )
        );
    }

    /**
     * Obtiene la instancia única del Logger (Singleton)
     */
    public static function getInstance(string $channel = 'clutchdata'): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self($channel);
        }
        return self::$instance;
    }

    /**
     * Obtiene el logger de Monolog directamente
     */
    public function getLogger(): MonologLogger
    {
        return $this->logger;
    }

    /**
     * Log de nivel DEBUG
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Log de nivel INFO
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Log de nivel WARNING
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * Log de nivel ERROR
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Log de nivel CRITICAL
     */
    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    /**
     * Log de inicio de scraping
     */
    public function logScrapingStart(string $scraper, string $url = ''): void
    {
        $this->info("Scraping started", [
            'scraper' => $scraper,
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Log de fin de scraping
     */
    public function logScrapingEnd(string $scraper, int $itemsCount, float $duration): void
    {
        $this->info("Scraping completed", [
            'scraper' => $scraper,
            'items_scraped' => $itemsCount,
            'duration_seconds' => round($duration, 2)
        ]);
    }

    /**
     * Log de error de scraping
     */
    public function logScrapingError(string $scraper, string $error, string $url = ''): void
    {
        $this->error("Scraping error", [
            'scraper' => $scraper,
            'error' => $error,
            'url' => $url
        ]);
    }

    /**
     * Log de acceso API
     */
    public function logApiAccess(string $endpoint, string $method, int $statusCode): void
    {
        $this->info("API access", [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * Log de error de base de datos
     */
    public function logDatabaseError(string $operation, string $error): void
    {
        $this->error("Database error", [
            'operation' => $operation,
            'error' => $error
        ]);
    }

    // Prevenir clonación y deserialización
    private function __clone()
    {
    }
    public function __wakeup()
    {
    }
}
