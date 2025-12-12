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



    protected function extractPlayerStats(Crawler $crawler): array
    {
        $rawPlayers = [];

        $crawler->filter('table.wikitable')->each(function (Crawler $table) use (&$rawPlayers) {
            $headers = $table->filter('th')->each(function ($th) {
                return trim($th->text());
            });

            $isStatsTable = false;
            $kIndex = -1;
            $dIndex = -1;
            $aIndex = -1;
            $nameIndex = 0;

            foreach ($headers as $i => $h) {
                $h = strtolower($h);
                if ($h === 'k' || $h === 'kills')
                    $kIndex = $i;
                if ($h === 'd' || $h === 'deaths')
                    $dIndex = $i;
                if ($h === 'a' || $h === 'assists')
                    $aIndex = $i;
                if ($h === 'player')
                    $nameIndex = $i;
            }

            if ($kIndex !== -1 && $dIndex !== -1) {
                $isStatsTable = true;
            }

            if ($isStatsTable) {
                $table->filter('tr')->each(function ($tr) use (&$rawPlayers, $kIndex, $dIndex, $aIndex, $nameIndex) {
                    $tds = $tr->filter('td');
                    if ($tds->count() > max($kIndex, $dIndex, $aIndex)) {
                        $nameNode = $tds->eq($nameIndex);
                        $player = trim($nameNode->text());

                        if (!empty($player)) {
                            $k = (int) trim($tds->eq($kIndex)->text());
                            $d = (int) trim($tds->eq($dIndex)->text());
                            $a = $aIndex !== -1 ? (int) trim($tds->eq($aIndex)->text()) : 0;

                            $rawPlayers[] = [
                                'name' => $player,
                                'kills' => $k,
                                'deaths' => $d,
                                'assists' => $a,
                                'agent' => ''
                            ];
                        }
                    }
                });
            }
        });

        // Aggregate players
        $aggregated = [];
        foreach ($rawPlayers as $p) {
            $name = $p['name'];
            if (!isset($aggregated[$name])) {
                $aggregated[$name] = [
                    'name' => $name,
                    'kills' => 0,
                    'deaths' => 0,
                    'assists' => 0,
                    'agents' => [] // No agents in CS2
                ];
            }
            $aggregated[$name]['kills'] += $p['kills'];
            $aggregated[$name]['deaths'] += $p['deaths'];
            $aggregated[$name]['assists'] += $p['assists'];
        }

        // Format for output
        $finalPlayers = [];
        foreach ($aggregated as $p) {
            $finalPlayers[] = [
                'name' => $p['name'],
                'kills' => $p['kills'],
                'deaths' => $p['deaths'],
                'assists' => $p['assists'],
                'agent' => '',
                'team' => 'Unknown'
            ];
        }

        return $finalPlayers;
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
            'streams' => [],
            'players' => []
        ];

        // Maps extraction (Improved)
        $crawler->filter('div.mapname')->each(function (Crawler $node) use (&$details) {
            $mapName = trim($node->text());
            $parent = $node->closest('div');

            if ($parent) {
                $t1Score = $parent->filter('.results-left')->count() ? $parent->filter('.results-left')->text() : '?';
                $t2Score = $parent->filter('.results-right')->count() ? $parent->filter('.results-right')->text() : '?';

                $details['maps'][] = [
                    'name' => $mapName,
                    'score1' => trim($t1Score),
                    'score2' => trim($t2Score)
                ];
            }
        });

        // Fallback for maps if mapname class not found
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

        // Extract Players
        $details['players'] = $this->extractPlayerStats($crawler);

        return $details;
    }
}
