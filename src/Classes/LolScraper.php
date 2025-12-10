<?php

namespace App\Classes;

class LolScraper extends LiquipediaScraper
{
    public function getGameType(): string
    {
        return 'lol';
    }

    public function scrapeMatches(): array
    {
        // Simulación
        return [
            [
                'team1' => 'T1',
                'team2' => 'Gen.G',
                'tournament' => 'LCK Spring',
                'time' => date('Y-m-d H:i:s', strtotime('+3 hours')),
                'game_type' => $this->getGameType()
            ]
        ];
    }
}
