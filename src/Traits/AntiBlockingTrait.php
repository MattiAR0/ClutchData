<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait con medidas anti-bloqueo para scrapers
 * 
 * Proporciona:
 * - Pool de User-Agents rotativos
 * - Delays variables con jitter
 * - Exponential backoff en errores
 * - Headers HTTP realistas
 * 
 * @package App\Traits
 */
trait AntiBlockingTrait
{
    /**
     * Pool de User-Agents reales de navegadores modernos
     */
    protected static array $userAgents = [
        // Chrome Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
        // Chrome Mac
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        // Firefox Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:119.0) Gecko/20100101 Firefox/119.0',
        // Firefox Mac
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.1; rv:121.0) Gecko/20100101 Firefox/121.0',
        // Safari
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        // Edge
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
        // Chrome Linux
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        // Firefox Linux
        'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
    ];

    /**
     * Referers alternativos para simular navegación normal
     */
    protected static array $referers = [
        'https://www.google.com/',
        'https://www.google.com/search?q=esports+matches',
        'https://www.google.com/search?q=csgo+results',
        'https://www.bing.com/',
        'https://www.bing.com/search?q=valorant+matches',
        'https://duckduckgo.com/',
        'https://www.reddit.com/',
        'https://twitter.com/',
        '',  // Acceso directo (sin referer)
    ];

    /**
     * Variaciones de Accept-Language
     */
    protected static array $acceptLanguages = [
        'en-US,en;q=0.9',
        'en-US,en;q=0.9,es;q=0.8',
        'en-GB,en;q=0.9,en-US;q=0.8',
        'en-US,en;q=0.8',
        'en,es;q=0.9',
        'en-US,en;q=0.9,de;q=0.7',
        'es-ES,es;q=0.9,en;q=0.8',
    ];

    /**
     * Contador de fallos consecutivos para backoff
     */
    protected int $consecutiveFailures = 0;

    /**
     * Máximo número de reintentos antes de fallar
     */
    protected int $maxRetries = 5;

    /**
     * Delay base en milisegundos (sobrescribir en cada scraper)
     */
    protected int $baseDelayMs = 2000;

    /**
     * Factor de jitter (variación aleatoria del delay)
     * 0.3 = ±30% del delay base
     */
    protected float $jitterFactor = 0.3;

    /**
     * Timestamp del último request
     */
    protected ?float $lastRequestTime = null;

    /**
     * Obtiene un User-Agent aleatorio del pool
     * 
     * @return string User-Agent seleccionado
     */
    protected function getRandomUserAgent(): string
    {
        return self::$userAgents[array_rand(self::$userAgents)];
    }

    /**
     * Genera headers HTTP realistas con valores aleatorios
     * 
     * @return array Headers para la petición
     */
    protected function getRandomHeaders(): array
    {
        $headers = [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => self::$acceptLanguages[array_rand(self::$acceptLanguages)],
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => (string) random_int(0, 1),
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-User' => '?1',
            'Cache-Control' => random_int(0, 1) ? 'max-age=0' : 'no-cache',
        ];

        // Añadir Referer aleatorio (70% de las veces)
        if (random_int(1, 10) <= 7) {
            $referer = self::$referers[array_rand(self::$referers)];
            if (!empty($referer)) {
                $headers['Referer'] = $referer;
                $headers['Sec-Fetch-Site'] = 'cross-site';
            } else {
                $headers['Sec-Fetch-Site'] = 'none';
            }
        } else {
            $headers['Sec-Fetch-Site'] = 'none';
        }

        return $headers;
    }

    /**
     * Calcula el delay con jitter aleatorio
     * 
     * @return int Delay en milisegundos
     */
    protected function calculateDelayWithJitter(): int
    {
        $jitter = $this->baseDelayMs * $this->jitterFactor;
        $minDelay = (int) max(500, $this->baseDelayMs - $jitter);
        $maxDelay = (int) ($this->baseDelayMs + $jitter);

        // Delay base con jitter + micro-delay adicional aleatorio (0-500ms)
        return random_int($minDelay, $maxDelay) + random_int(0, 500);
    }

    /**
     * Calcula el delay con exponential backoff
     * Se usa cuando hay errores consecutivos
     * 
     * @return int Delay en milisegundos
     */
    protected function calculateBackoffDelay(): int
    {
        if ($this->consecutiveFailures === 0) {
            return $this->calculateDelayWithJitter();
        }

        // Exponential backoff: 2^failures * 1000ms, máximo 60 segundos
        $backoffMs = min(60000, (int) pow(2, $this->consecutiveFailures) * 1000);

        // Añadir jitter al backoff para evitar thundering herd
        $jitter = (int) ($backoffMs * 0.2);
        $backoffMs += random_int(-$jitter, $jitter);

        return max($backoffMs, $this->calculateDelayWithJitter());
    }

    /**
     * Aplica rate limiting inteligente con jitter y backoff
     */
    protected function applySmartRateLimit(): void
    {
        if ($this->lastRequestTime !== null) {
            $elapsed = (microtime(true) - $this->lastRequestTime) * 1000;
            $targetDelay = $this->calculateBackoffDelay();

            if ($elapsed < $targetDelay) {
                $sleepTime = (int) max(0, ($targetDelay - $elapsed) * 1000);
                if ($sleepTime > 0) {
                    usleep($sleepTime);
                }
            }
        }
        $this->lastRequestTime = microtime(true);
    }

    /**
     * Registra una petición exitosa (resetea el contador de fallos)
     */
    protected function registerSuccess(): void
    {
        $this->consecutiveFailures = 0;
    }

    /**
     * Registra una petición fallida (incrementa el contador)
     */
    protected function registerFailure(): void
    {
        $this->consecutiveFailures = min($this->consecutiveFailures + 1, $this->maxRetries);
    }

    /**
     * Verifica si debemos reintentar tras un fallo
     * 
     * @return bool True si debemos reintentar
     */
    protected function shouldRetry(): bool
    {
        return $this->consecutiveFailures < $this->maxRetries;
    }

    /**
     * Obtiene el número de fallos consecutivos actuales
     * 
     * @return int Número de fallos
     */
    protected function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    /**
     * Resetea el estado del anti-blocking
     */
    protected function resetAntiBlockingState(): void
    {
        $this->consecutiveFailures = 0;
        $this->lastRequestTime = null;
    }
}
