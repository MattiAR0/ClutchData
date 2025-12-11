<?php

use PHPUnit\Framework\TestCase;
use App\Classes\ValorantScraper;

class ScraperTest extends TestCase
{
    public function testValorantScraperReturnsArray()
    {
        $scraper = new ValorantScraper();
        $matches = $scraper->scrapeMatches();

        $this->assertIsArray($matches);
        $this->assertNotEmpty($matches);

        $firstMatch = $matches[0];
        $this->assertArrayHasKey('team1', $firstMatch);
        $this->assertArrayHasKey('team2', $firstMatch);
        $this->assertArrayHasKey('game_type', $firstMatch);
        $this->assertEquals('valorant', $firstMatch['game_type']);
    }
}
