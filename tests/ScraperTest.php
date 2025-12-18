<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Classes\ValorantScraper;

/**
 * Test de integración para ValorantScraper
 * Nota: Este test requiere conexión a internet
 */
class ScraperTest extends TestCase
{
    /**
     * Test: El scraper devuelve un array (puede estar vacío si no hay conexión)
     */
    public function testValorantScraperReturnsArray(): void
    {
        $scraper = new ValorantScraper();
        $matches = $scraper->scrapeMatches();

        $this->assertIsArray($matches);

        // Solo validamos estructura si hay resultados
        // (puede fallar por falta de conexión o cambios en Liquipedia)
        if (!empty($matches)) {
            $firstMatch = $matches[0];
            $this->assertArrayHasKey('team1', $firstMatch);
            $this->assertArrayHasKey('team2', $firstMatch);
            $this->assertArrayHasKey('game_type', $firstMatch);
            $this->assertEquals('valorant', $firstMatch['game_type']);
        } else {
            // Si está vacío, simplemente marcamos como advertencia
            $this->markTestSkipped('No se pudieron obtener partidos (sin conexión o sin datos)');
        }
    }

    /**
     * Test: El tipo de juego es correcto
     */
    public function testScraperGameType(): void
    {
        $scraper = new ValorantScraper();
        $this->assertEquals('valorant', $scraper->getGameType());
    }
}

