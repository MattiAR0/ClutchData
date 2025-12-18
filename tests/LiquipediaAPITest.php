<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Classes\LiquipediaAPI;
use App\Exceptions\ScrapingException;

/**
 * Test unitario para LiquipediaAPI
 * Verifica rate limiting, construcción de requests, y manejo de errores
 */
class LiquipediaAPITest extends TestCase
{
    private LiquipediaAPI $api;

    protected function setUp(): void
    {
        $this->api = new LiquipediaAPI();
    }

    /**
     * Test: El health check funciona correctamente
     */
    public function testHealthCheckReturnsBoolean(): void
    {
        // This test actually connects to the API
        // Skip if no internet or API is down
        $result = $this->api->healthCheck('valorant');
        $this->assertIsBool($result);
    }

    /**
     * Test: Game types desconocidos lanzan excepción
     */
    public function testUnknownGameTypeThrowsException(): void
    {
        $this->expectException(ScrapingException::class);

        // Use reflection to call the private request method
        $reflection = new ReflectionClass($this->api);
        $method = $reflection->getMethod('request');
        $method->setAccessible(true);

        $method->invoke($this->api, 'unknown_game', ['action' => 'query'], false);
    }

    /**
     * Test: El rate limiting funciona (método interno)
     * Este test verifica que el método de rate limit existe y es callable
     */
    public function testRateLimitMethodExists(): void
    {
        $reflection = new ReflectionClass($this->api);
        $method = $reflection->getMethod('respectRateLimit');

        $this->assertTrue($method->isPrivate());
        $this->assertCount(1, $method->getParameters());
    }

    /**
     * Test: Los endpoints de API están correctamente definidos
     */
    public function testApiEndpointsAreDefined(): void
    {
        $reflection = new ReflectionClass($this->api);
        $constant = $reflection->getConstant('API_ENDPOINTS');

        $this->assertIsArray($constant);
        $this->assertArrayHasKey('valorant', $constant);
        $this->assertArrayHasKey('lol', $constant);
        $this->assertArrayHasKey('cs2', $constant);

        // Verify endpoints are valid URLs
        foreach ($constant as $game => $url) {
            $this->assertStringStartsWith('https://liquipedia.net/', $url);
            $this->assertStringEndsWith('/api.php', $url);
        }
    }

    /**
     * Test: Las páginas de partidos están definidas para cada juego
     */
    public function testMatchPagesAreDefined(): void
    {
        $reflection = new ReflectionClass($this->api);
        $constant = $reflection->getConstant('MATCH_PAGES');

        $this->assertIsArray($constant);
        $this->assertArrayHasKey('valorant', $constant);
        $this->assertArrayHasKey('lol', $constant);
        $this->assertArrayHasKey('cs2', $constant);
    }

    /**
     * Test: La constante de rate limit tiene el valor correcto
     */
    public function testRateLimitConstantIsCorrect(): void
    {
        $reflection = new ReflectionClass($this->api);

        $rateLimit = $reflection->getConstant('RATE_LIMIT_SECONDS');
        $this->assertEquals(2.0, $rateLimit);

        $parseRateLimit = $reflection->getConstant('PARSE_RATE_LIMIT_SECONDS');
        $this->assertEquals(5.0, $parseRateLimit);
    }
}
