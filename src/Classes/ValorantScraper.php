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
                            'game_type' => $this->getGameType(),
                            'team1_score' => $this->extractScore($node, true),
                            'team2_score' => $this->extractScore($node, false),
                            'match_status' => $this->detectStatus($node),
                            'match_url' => $this->extractMatchUrl($node, '/valorant/Match:')
                        ];
                    }
                } catch (Exception $e) {
                    // Skip malformed rows
                }
            });
        }

        return $matches;
    }

    public function scrapeMatchDetails(string $url): array
    {
        // Parse path
        $path = parse_url($url, PHP_URL_PATH);
        $html = $this->fetch($path);

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $details = [
            'maps' => [],
            'streams' => []
        ];

        // Valorant map scores
        $crawler->filter('div.vm-stats-game')->each(function (Crawler $node) use (&$details) {
            $mapHeader = $node->filter('.vm-stats-game-header');
            $mapName = $mapHeader->count() ? trim($mapHeader->text()) : 'Unknown Map';

            $scoreNode = $node->filter('.vm-stats-game-header-score');
            $score = $scoreNode->count() ? trim($scoreNode->text()) : '';

            if ($score) {
                $parts = explode('-', $score);
                $details['maps'][] = [
                    'name' => $mapName,
                    'score1' => isset($parts[0]) ? trim($parts[0]) : '0',
                    'score2' => isset($parts[1]) ? trim($parts[1]) : '0'
                ];
            }
        });

        if (empty($details['maps'])) {
            $crawler->filter('div.match-history-game')->each(function (Crawler $node) use (&$details) {
                $mapNode = $node->filter('.match-history-map');
                $mapName = $mapNode->count() ? trim($mapNode->text()) : 'Unknown';

                $scoreNode = $node->filter('.match-history-score');
                $score = $scoreNode->count() ? $scoreNode->text() : '';

                if ($score) {
                    $parts = explode('-', $score);
                    $details['maps'][] = [
                        'name' => $mapName,
                        'score1' => isset($parts[0]) ? trim($parts[0]) : '0',
                        'score2' => isset($parts[1]) ? trim($parts[1]) : '0'
                    ];
                }
            });
        }

        return $details;
    }
}
