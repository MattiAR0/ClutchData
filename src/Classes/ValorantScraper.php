<?php

namespace App\Classes;

use Symfony\Component\DomCrawler\Crawler;

class ValorantScraper extends LiquipediaScraper
{
    public function getGameType(): string
    {
        return 'valorant';
    }

    public function scrapeMatches(): array
    {
        // NOTA: Implementación de ejemplo dummy para skeleton.
        // En producción, aquí haríamos $this->client->get(...) y parsearíamos con Crawler.

        // Simulación de scraping
        return [
            [
                'team1' => 'Sentinels',
                'team2' => 'LOUD',
                'tournament' => 'VCT Americas',
                'time' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'game_type' => $this->getGameType()
            ],
            [
                'team1' => 'Fnatic',
                'team2' => 'Liquid',
                'tournament' => 'VCT EMEA',
                'time' => date('Y-m-d H:i:s', strtotime('+2 days')),
                'game_type' => $this->getGameType()
            ]
        ];
    }
}
