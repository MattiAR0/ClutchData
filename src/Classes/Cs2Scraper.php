<?php

declare(strict_types=1);

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
                            'match_url' => $this->extractMatchUrl($node, '/counterstrike/Match:'),
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



    /**
     * Extrae stats de jugadores - ahora soporta extracción por mapa
     */
    protected function extractPlayerStats(Crawler $crawler): array
    {
        $rawPlayers = [];

        $crawler->filter('table.wikitable')->each(function (Crawler $table) use (&$rawPlayers) {
            $headers = $table->filter('th')->each(fn($th) => trim($th->text()));

            $kIndex = $dIndex = $aIndex = -1;
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
                $table->filter('tr')->each(function ($tr) use (&$rawPlayers, $kIndex, $dIndex, $aIndex, $nameIndex) {
                    $tds = $tr->filter('td');
                    if ($tds->count() > max($kIndex, $dIndex)) {
                        $nameNode = $tds->eq($nameIndex);
                        $player = $nameNode->filter('a')->count()
                            ? trim($nameNode->filter('a')->text())
                            : trim($nameNode->text());

                        if (!empty($player) && $player !== 'Player') {
                            $rawPlayers[] = [
                                'name' => $player,
                                'kills' => (int) trim($tds->eq($kIndex)->text()),
                                'deaths' => (int) trim($tds->eq($dIndex)->text()),
                                'assists' => $aIndex !== -1 ? (int) trim($tds->eq($aIndex)->text()) : 0,
                                'agent' => '',
                                'team' => 'Unknown',
                                'data_source' => 'liquipedia'
                            ];
                        }
                    }
                });
            }
        });

        return $rawPlayers;
    }

    /**
     * Extrae stats por mapa desde múltiples tablas
     */
    protected function extractPlayerStatsByMap(Crawler $crawler): array
    {
        $playersByMap = [];
        $tables = $crawler->filter('table.wikitable');

        if ($tables->count() <= 1) {
            return $playersByMap;
        }

        // Buscar headers de mapa antes de cada tabla
        $mapNames = [];
        $crawler->filter('.mw-headline, .match-bm-game-header, h3, h4')->each(function (Crawler $header) use (&$mapNames) {
            $text = trim($header->text());
            // Detectar nombres de mapa comunes de CS2
            if (preg_match('/(Mirage|Inferno|Dust2|Nuke|Ancient|Anubis|Overpass|Vertigo|Train)/i', $text, $matches)) {
                $mapNames[] = $matches[1];
            }
        });

        $tables->each(function (Crawler $table, $index) use (&$playersByMap, $mapNames) {
            if ($index === 0)
                return; // Skip first table (usually overall)

            $mapName = $mapNames[$index - 1] ?? "Map " . $index;
            $players = [];

            $headers = $table->filter('th')->each(fn($th) => trim($th->text()));
            $kIndex = $dIndex = $aIndex = -1;
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
                $table->filter('tr')->each(function ($tr) use (&$players, $kIndex, $dIndex, $aIndex, $nameIndex) {
                    $tds = $tr->filter('td');
                    if ($tds->count() > max($kIndex, $dIndex)) {
                        $nameNode = $tds->eq($nameIndex);
                        $player = $nameNode->filter('a')->count()
                            ? trim($nameNode->filter('a')->text())
                            : trim($nameNode->text());

                        if (!empty($player) && $player !== 'Player') {
                            $players[] = [
                                'name' => $player,
                                'kills' => (int) trim($tds->eq($kIndex)->text()),
                                'deaths' => (int) trim($tds->eq($dIndex)->text()),
                                'assists' => $aIndex !== -1 ? (int) trim($tds->eq($aIndex)->text()) : 0,
                                'agent' => '',
                                'team' => 'Unknown',
                                'data_source' => 'liquipedia'
                            ];
                        }
                    }
                });
            }

            if (!empty($players)) {
                $playersByMap[$mapName] = $players;
            }
        });

        return $playersByMap;
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
            'players' => [],
            'players_by_map' => []
        ];

        // Maps extraction
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

        // Fallback for maps
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

        // Extract overall stats
        $details['players'] = $this->extractPlayerStats($crawler);

        // Extract per-map stats
        $details['players_by_map'] = $this->extractPlayerStatsByMap($crawler);

        return $details;
    }
}

