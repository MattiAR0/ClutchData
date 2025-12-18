<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Classes\LiquipediaScraper;
use App\Classes\ValorantScraper;

/**
 * Test unitario para el sistema de scraping
 * Valida la lógica de detección de regiones e importancia
 */
class LiquipediaScraperTest extends TestCase
{
    private ValorantScraper $scraper;

    protected function setUp(): void
    {
        $this->scraper = new ValorantScraper();
    }

    /**
     * Test: El tipo de juego se retorna correctamente
     */
    public function testGetGameTypeReturnsValorant(): void
    {
        $gameType = $this->scraper->getGameType();
        $this->assertEquals('valorant', $gameType);
    }

    /**
     * Test: Detección de región para torneos de Americas
     */
    public function testDetectRegionAmericas(): void
    {
        $reflection = new ReflectionClass($this->scraper);
        // Acceder a través del padre (LiquipediaScraper)
        $method = $reflection->getParentClass()->getMethod('detectRegion');
        $method->setAccessible(true);

        $region = $method->invoke($this->scraper, 'VCT Americas Kickoff');
        $this->assertEquals('Americas', $region);

        $region = $method->invoke($this->scraper, 'CBLOL Split 1');
        $this->assertEquals('Americas', $region);
    }

    /**
     * Test: Detección de región para torneos de EMEA
     */
    public function testDetectRegionEMEA(): void
    {
        $reflection = new ReflectionClass($this->scraper);
        $method = $reflection->getParentClass()->getMethod('detectRegion');
        $method->setAccessible(true);

        $region = $method->invoke($this->scraper, 'VCT EMEA Stage 1');
        $this->assertEquals('EMEA', $region);

        $region = $method->invoke($this->scraper, 'LEC Winter Split');
        $this->assertEquals('EMEA', $region);
    }

    /**
     * Test: Detección de región para torneos de Pacific
     */
    public function testDetectRegionPacific(): void
    {
        $reflection = new ReflectionClass($this->scraper);
        $method = $reflection->getParentClass()->getMethod('detectRegion');
        $method->setAccessible(true);

        $region = $method->invoke($this->scraper, 'VCT Pacific League');
        $this->assertEquals('Pacific', $region);

        $region = $method->invoke($this->scraper, 'LCK Spring 2024');
        $this->assertEquals('Pacific', $region);
    }

    /**
     * Test: Detección de región para torneos internacionales
     */
    public function testDetectRegionInternational(): void
    {
        $reflection = new ReflectionClass($this->scraper);
        $method = $reflection->getParentClass()->getMethod('detectRegion');
        $method->setAccessible(true);

        $region = $method->invoke($this->scraper, 'VCT Champions 2024');
        $this->assertEquals('International', $region);

        $region = $method->invoke($this->scraper, 'Valorant Masters Bangkok');
        $this->assertEquals('International', $region);
    }

    /**
     * Test: Cálculo de importancia para torneos tier 1
     */
    public function testCalculateImportanceHighTier(): void
    {
        $reflection = new ReflectionClass($this->scraper);
        $method = $reflection->getParentClass()->getMethod('calculateImportance');
        $method->setAccessible(true);

        // Champions = International (90) + WORLD (50) = 140+
        $importance = $method->invoke($this->scraper, 'VCT Champions Seoul', 'International');
        $this->assertGreaterThan(100, $importance);
    }

    /**
     * Test: Cálculo de importancia para torneos regionales
     */
    public function testCalculateImportanceRegional(): void
    {
        $reflection = new ReflectionClass($this->scraper);
        $method = $reflection->getParentClass()->getMethod('calculateImportance');
        $method->setAccessible(true);

        // Regional tournament
        $importance = $method->invoke($this->scraper, 'VCT EMEA League', 'EMEA');
        $this->assertGreaterThanOrEqual(50, $importance);
        $this->assertLessThan(200, $importance);
    }
}
