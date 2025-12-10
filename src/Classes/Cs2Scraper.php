<?php

namespace App\Classes;

class Cs2Scraper extends LiquipediaScraper
{
    public function getGameType(): string
    {
        return 'cs2';
    }

    public function scrapeMatches(): array
    {
        // Simulación
        return [
            [
                'team1' => 'FaZe',
                'team2' => 'Vitality',
                'tournament' => 'PGL Major',
                'time' => date('Y-m-d H:i:s', strtotime('+5 days')),
                'game_type' => $this->getGameType()
            ]
        ];
    }
}
