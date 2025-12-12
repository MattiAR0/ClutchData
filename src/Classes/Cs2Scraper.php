<?php

namespace App\Classes;

use Symfony\Component\DomCrawler\Crawler;
use Exception;

class Cs2Scraper extends LiquipediaScraper
{
    public function getGameType(): string
    {
        return 'cs2';
    }

    public function scrapeMatches(): array
    {
        $html = $this->fetch('/counterstrike/Liquipedia:Matches');

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
                            'match_url' => $this->extractMatchUrl($node, '/counterstrike/Match:')
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
        // Remove domain if present to get relative path for fetch
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

        // Try to find map scores. 
        // CS2 Liquipedia often uses .match-content or similar blocks for maps.
        // A common pattern for map list is div.vod-popup or .bracket-popup-body > .match-maps
        // Or simply look for text "Map X: ..."

        // This is a simplified extractor looking for map headers
        $crawler->filter('.match-content div[style*="width:23%"]')->each(function (Crawler $node) use (&$details) {
            $text = $node->text();
            if (str_contains($text, 'Map')) {
                // Try to get score sibling
            }
        });

        // let's try a more robust generic approach for now:
        // Find elements with class "mapname"
        $crawler->filter('.mapname')->each(function (Crawler $node) use (&$details) {
            $mapName = trim($node->text());

            // Try to find the score nearby. Usually parent has the score.
            $parent = $node->closest('div');
            if ($parent) {
                // Try to find scores
                $t1Score = $parent->filter('.results-left')->count() ? $parent->filter('.results-left')->text() : '?';
                $t2Score = $parent->filter('.results-right')->count() ? $parent->filter('.results-right')->text() : '?';

                if ($mapName) {
                    $details['maps'][] = [
                        'name' => $mapName,
                        'score1' => trim($t1Score),
                        'score2' => trim($t2Score)
                    ];
                }
            }
        });

        // If empty, try another common selector for "Match Info" box scores
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
