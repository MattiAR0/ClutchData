<?php

namespace App\Classes;

use Symfony\Component\DomCrawler\Crawler;
use Exception;

class LolScraper extends LiquipediaScraper
{
    public function getGameType(): string
    {
        return 'lol';
    }

    public function scrapeMatches(): array
    {
        $html = $this->fetch('/leagueoflegends/Liquipedia:Matches');

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $matches = [];

        $matchNodes = $crawler->filter('div.match-info');

        if ($matchNodes->count() > 0) {
            $matchNodes->each(function (Crawler $node) use (&$matches) {
                try {
                    $team1Node = $node->filter('.match-info-header-opponent-left .name');
                    $team1 = $team1Node->count() ? $team1Node->text() : 'TBD';

                    $team2Node = $node->filter('.match-info-header-opponent:not(.match-info-header-opponent-left) .name');
                    $team2 = $team2Node->count() ? $team2Node->text() : 'TBD';

                    $timeNode = $node->filter('.timer-object');
                    $timestamp = $timeNode->count() ? $timeNode->attr('data-timestamp') : null;
                    $time = $timestamp ? date('Y-m-d H:i:s', (int) $timestamp) : date('Y-m-d H:i:s');

                    $tournament = 'Unknown Tournament';
                    $tournamentNode = $node->filter('.match-info-tournament-name');
                    if ($tournamentNode->count()) {
                        $tournament = $tournamentNode->text();
                    }

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
                            'match_url' => $this->extractMatchUrl($node, '/leagueoflegends/Match:')
                        ];
                    }
                } catch (Exception $e) {
                    // Skip
                }
            });
        }

        return $matches;
    }

    public function scrapeMatchDetails(string $url): array
    {
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

        // LoL games
        $crawler->filter('div.match-history-game')->each(function (Crawler $node) use (&$details) {
            $header = $node->filter('.match-history-game-header');
            $mapName = $header->count() ? trim($header->text()) : 'Game';

            // Try to find score or winner
            $scoreText = $header->count() ? $header->text() : '';
            $score1 = '-';
            $score2 = '-';

            if (str_contains(strtolower($scoreText), 'winner')) {
                $score1 = 'Win'; // Simplification
            }

            $details['maps'][] = [
                'name' => $mapName,
                'score1' => $score1,
                'score2' => $score2
            ];
        });

        return $details;
    }
}
