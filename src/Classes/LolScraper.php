<?php

declare(strict_types=1);

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
                            'match_url' => $this->extractMatchUrl($node, '/leagueoflegends/Match:'),
                            'match_importance' => $this->calculateImportance($tournament, $this->detectRegion($tournament))
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

        // LoL Liquipedia stats often in match-history-stats table
        // Headers: Player, K, D, A, CS, G

        $crawler->filter('table.match-history-stats, table.wikitable')->each(function (Crawler $table) use (&$rawPlayers) {
            $headers = $table->filter('th')->each(function ($th) {
                return trim($th->text());
            });

            $kIndex = -1;
            $dIndex = -1;
            $aIndex = -1;
            $nameIndex = 0;
            $champIndex = -1;

            foreach ($headers as $i => $h) {
                $h = strtolower($h);
                if ($h === 'k')
                    $kIndex = $i;
                if ($h === 'd')
                    $dIndex = $i;
                if ($h === 'a')
                    $aIndex = $i;
                if ($h === 'champion' || $h === '')
                    $champIndex = $i; // Sometimes champion is an image column without header text
                if ($h === 'player')
                    $nameIndex = $i;
            }
            // Heuristic for champion column if empty header
            if ($champIndex === -1 && isset($headers[0]) && $headers[0] === '')
                $champIndex = 0;


            if ($kIndex !== -1 && $dIndex !== -1 && $aIndex !== -1) {
                $table->filter('tr')->each(function ($tr) use (&$rawPlayers, $kIndex, $dIndex, $aIndex, $nameIndex, $champIndex) {
                    $tds = $tr->filter('td');
                    if ($tds->count() > max($kIndex, $dIndex, $aIndex)) {
                        $nameNode = $tds->eq($nameIndex);
                        $player = trim($nameNode->text());

                        if (!empty($player) && $player !== 'Total') {
                            $champion = '';
                            if ($champIndex !== -1 && $tds->eq($champIndex)->count()) {
                                $img = $tds->eq($champIndex)->filter('img');
                                if ($img->count()) {
                                    $champion = $img->attr('alt') ?? $img->attr('title') ?? '';
                                }
                            }

                            $rawPlayers[] = [
                                'name' => $player,
                                'kills' => (int) trim($tds->eq($kIndex)->text()),
                                'deaths' => (int) trim($tds->eq($dIndex)->text()),
                                'assists' => (int) trim($tds->eq($aIndex)->text()),
                                'agent' => $champion // Using 'agent' field for champion to keep generic
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
                    'agents' => []
                ];
            }
            $aggregated[$name]['kills'] += $p['kills'];
            $aggregated[$name]['deaths'] += $p['deaths'];
            $aggregated[$name]['assists'] += $p['assists'];

            if (!empty($p['agent']) && !in_array($p['agent'], $aggregated[$name]['agents'])) {
                $aggregated[$name]['agents'][] = $p['agent'];
            }
        }

        // Format for output
        $finalPlayers = [];
        foreach ($aggregated as $p) {
            $finalPlayers[] = [
                'name' => $p['name'],
                'kills' => $p['kills'],
                'deaths' => $p['deaths'],
                'assists' => $p['assists'],
                'agent' => implode(', ', $p['agents']),
                'team' => 'Unknown'
            ];
        }

        return $finalPlayers;
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
            'streams' => [],
            'players' => []
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

        $details['players'] = $this->extractPlayerStats($crawler);

        return $details;
    }
}
