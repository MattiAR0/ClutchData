<?php

namespace App\Classes;

use Symfony\Component\DomCrawler\Crawler;
use Exception;

class ValorantScraper extends LiquipediaScraper
{
    public function getGameType(): string
    {
        return 'valorant';
    }

    public function scrapeMatches(): array
    {
        // Usamos la página de lista de partidos oficiales de Liquipedia
        $html = $this->fetch('/valorant/Liquipedia:Matches');

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $matches = [];

        // Updated selector for new Liquipedia structure (div.match-info)
        $matchNodes = $crawler->filter('div.match-info');

        if ($matchNodes->count() > 0) {
            $matchNodes->each(function (Crawler $node) use (&$matches) {
                try {
                    // Teams are in .match-info-header-opponent
                    // Left team (Team 1) usually has .match-info-header-opponent-left
                    $team1Node = $node->filter('.match-info-header-opponent-left .name');
                    $team1 = $team1Node->count() ? $team1Node->text() : 'TBD';

                    // Right team (Team 2) is the opponent that is NOT left
                    $team2Node = $node->filter('.match-info-header-opponent:not(.match-info-header-opponent-left) .name');
                    $team2 = $team2Node->count() ? $team2Node->text() : 'TBD';

                    // Time
                    $timeNode = $node->filter('.timer-object');
                    $timestamp = $timeNode->count() ? $timeNode->attr('data-timestamp') : null;
                    $time = $timestamp ? date('Y-m-d H:i:s', (int) $timestamp) : date('Y-m-d H:i:s');

                    // Tournament Name
                    $tournament = 'Unknown Tournament';
                    $tournamentNode = $node->filter('.match-info-tournament-name');
                    if ($tournamentNode->count()) {
                        $tournament = $tournamentNode->text();
                    }

                    // Only add if we have at least one team
                    if ($team1 !== 'TBD' || $team2 !== 'TBD') {
                        $matches[] = [
                            'team1' => trim($team1),
                            'team2' => trim($team2),
                            'tournament' => trim($tournament),
                            'region' => $this->detectRegion($tournament),
                            'time' => $time,
                            'game_type' => $this->getGameType()
                        ];
                    }
                } catch (Exception $e) {
                    // Skip malformed rows
                }
            });
        }

        return $matches;
    }
}
