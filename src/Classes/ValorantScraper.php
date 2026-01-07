<?php

declare(strict_types=1);

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
                            'match_url' => $this->extractMatchUrl($node, '/valorant/Match:'),
                            'match_importance' => $this->calculateImportance($tournament, $this->detectRegion($tournament))
                        ];
                    }
                } catch (Exception $e) {
                    // Skip malformed rows
                }
            });
        }

        return $matches;
    }



    /**
     * Extrae stats de jugadores - ahora soporta extracción por mapa
     * @param Crawler $crawler Full page crawler
     * @param string|null $mapContext Si se especifica, extrae solo de ese mapa
     * @return array Stats de jugadores
     */
    protected function extractPlayerStats(Crawler $crawler, ?string $mapContext = null): array
    {
        $rawPlayers = [];

        // Determinar qué wrapper usar
        $wrapperSelector = '.match-bm-players-wrapper';
        $wrappers = $crawler->filter($wrapperSelector);

        if ($wrappers->count() === 0) {
            // Fallback a tablas
            return $this->extractPlayerStatsFromTables($crawler);
        }

        // Si se especifica mapa, intentar encontrar el wrapper correcto
        // Los mapas suelen estar ordenados: [Overall, Map1, Map2, ...]
        $targetWrapper = $mapContext === null ? $wrappers->first() : $wrappers->first();

        if ($targetWrapper->count() > 0) {
            $targetWrapper->filter('.match-bm-players-team')->each(function (Crawler $teamNode) use (&$rawPlayers) {
                $teamName = 'Unknown Team';
                $header = $teamNode->filter('.match-bm-players-team-header');
                if ($header->count()) {
                    $text = trim($header->text());
                    if (!empty($text)) {
                        $teamName = $text;
                    } else {
                        $img = $header->filter('img')->last();
                        if ($img->count()) {
                            $teamName = $img->attr('alt') ?? $img->attr('title') ?? 'Unknown Team';
                        }
                        if ($teamName === 'Unknown Team') {
                            $link = $header->filter('a')->first();
                            if ($link->count()) {
                                $teamName = $link->attr('title') ?? 'Unknown Team';
                            }
                        }
                    }
                }

                $teamNode->filter('div.match-bm-players-player')->each(function (Crawler $row) use (&$rawPlayers, $teamName) {
                    $nameNode = $row->filter('.match-bm-players-player-name');
                    $player = $nameNode->filter('a')->count()
                        ? trim($nameNode->filter('a')->text())
                        : ($nameNode->count() ? trim($nameNode->text()) : 'Unknown');

                    $agent = '';
                    $agentImg = $nameNode->filter('img')->last();
                    if ($agentImg->count()) {
                        $agent = $agentImg->attr('alt') ?? $agentImg->attr('title') ?? '';
                    }

                    $k = $d = $a = 0;
                    $row->filter('.match-bm-players-player-stat')->each(function (Crawler $stat) use (&$k, &$d, &$a) {
                        $titleNode = $stat->filter('.match-bm-players-player-stat-title');
                        $title = $titleNode->count() ? trim($titleNode->text()) : '';

                        if (str_contains($title, 'KDA')) {
                            $dataNode = $stat->filter('.match-bm-players-player-stat-data');
                            $data = $dataNode->count() ? trim($dataNode->text()) : '';
                            $parts = explode('/', $data);
                            if (count($parts) >= 3) {
                                $k = (int) trim($parts[0]);
                                $d = (int) trim($parts[1]);
                                $a = (int) trim($parts[2]);
                            }
                        }
                    });

                    if ($player !== 'Unknown' && ($k > 0 || $d > 0 || $a > 0)) {
                        $rawPlayers[] = [
                            'name' => $player,
                            'kills' => $k,
                            'deaths' => $d,
                            'assists' => $a,
                            'agent' => $agent,
                            'team' => $teamName,
                            'data_source' => 'liquipedia'
                        ];
                    }
                });
            });
        }

        return $rawPlayers;
    }

    /**
     * Extrae stats por cada mapa desde los wrappers
     * @return array ['mapName' => [players...], ...]
     */
    protected function extractPlayerStatsByMap(Crawler $crawler): array
    {
        $playersByMap = [];
        $wrappers = $crawler->filter('.match-bm-players-wrapper');

        if ($wrappers->count() <= 1) {
            return $playersByMap; // Solo hay overall, no hay por mapa
        }

        // Obtener nombres de mapas de las tabs
        $mapNames = [];
        $crawler->filter('.match-bm-lol-stats-game-nav-item, .match-bm-game-nav-item')->each(function (Crawler $tab, $i) use (&$mapNames) {
            $mapName = trim($tab->text());
            if (!empty($mapName) && strtolower($mapName) !== 'overall' && strtolower($mapName) !== 'all') {
                $mapNames[] = $mapName;
            }
        });

        // Si no encontramos tabs, intentar con headers de mapa
        if (empty($mapNames)) {
            $crawler->filter('.match-bm-lol-stats-header, .match-bm-game-header')->each(function (Crawler $header) use (&$mapNames) {
                $text = trim($header->text());
                if (!empty($text) && !str_contains(strtolower($text), 'total')) {
                    $mapNames[] = preg_replace('/\s*\d+-\d+\s*$/', '', $text); // Remove score
                }
            });
        }

        // Iterar wrappers (skip first if it's overall)
        $wrappers->each(function (Crawler $wrapper, $index) use (&$playersByMap, $mapNames) {
            // Determinar nombre del mapa
            $mapName = $mapNames[$index] ?? "Map " . ($index + 1);

            // Skip if this looks like an overall/total wrapper
            if ($index === 0 && str_contains(strtolower($mapName), 'overall')) {
                return;
            }

            $players = [];
            $wrapper->filter('.match-bm-players-team')->each(function (Crawler $teamNode) use (&$players) {
                $teamName = 'Unknown Team';
                $header = $teamNode->filter('.match-bm-players-team-header');
                if ($header->count()) {
                    $text = trim($header->text());
                    if (!empty($text)) {
                        $teamName = $text;
                    } else {
                        $link = $header->filter('a')->first();
                        if ($link->count()) {
                            $teamName = $link->attr('title') ?? 'Unknown Team';
                        }
                    }
                }

                $teamNode->filter('div.match-bm-players-player')->each(function (Crawler $row) use (&$players, $teamName) {
                    $nameNode = $row->filter('.match-bm-players-player-name');
                    $player = $nameNode->filter('a')->count()
                        ? trim($nameNode->filter('a')->text())
                        : ($nameNode->count() ? trim($nameNode->text()) : 'Unknown');

                    $agent = '';
                    $agentImg = $nameNode->filter('img')->last();
                    if ($agentImg->count()) {
                        $agent = $agentImg->attr('alt') ?? $agentImg->attr('title') ?? '';
                    }

                    $k = $d = $a = 0;
                    $row->filter('.match-bm-players-player-stat')->each(function (Crawler $stat) use (&$k, &$d, &$a) {
                        $titleNode = $stat->filter('.match-bm-players-player-stat-title');
                        $title = $titleNode->count() ? trim($titleNode->text()) : '';

                        if (str_contains($title, 'KDA')) {
                            $dataNode = $stat->filter('.match-bm-players-player-stat-data');
                            $data = $dataNode->count() ? trim($dataNode->text()) : '';
                            $parts = explode('/', $data);
                            if (count($parts) >= 3) {
                                $k = (int) trim($parts[0]);
                                $d = (int) trim($parts[1]);
                                $a = (int) trim($parts[2]);
                            }
                        }
                    });

                    if ($player !== 'Unknown' && ($k > 0 || $d > 0 || $a > 0)) {
                        $players[] = [
                            'name' => $player,
                            'kills' => $k,
                            'deaths' => $d,
                            'assists' => $a,
                            'agent' => $agent,
                            'team' => $teamName,
                            'data_source' => 'liquipedia'
                        ];
                    }
                });
            });

            if (!empty($players)) {
                $playersByMap[$mapName] = $players;
            }
        });

        return $playersByMap;
    }

    /**
     * Fallback: extrae stats desde tablas wikitable
     */
    protected function extractPlayerStatsFromTables(Crawler $crawler): array
    {
        $rawPlayers = [];

        $crawler->filter('table.wikitable, table.vm-stats-game-table')->each(function (Crawler $table) use (&$rawPlayers) {
            $headers = $table->filter('th')->each(fn($th) => trim($th->text()));

            $kIndex = $dIndex = $aIndex = $agentIndex = -1;
            $nameIndex = 0;

            foreach ($headers as $i => $h) {
                $h = strtolower($h);
                if ($h === 'k')
                    $kIndex = $i;
                if ($h === 'd')
                    $dIndex = $i;
                if ($h === 'a')
                    $aIndex = $i;
                if ($h === 'agent')
                    $agentIndex = $i;
                if ($h === 'player')
                    $nameIndex = $i;
            }

            if ($kIndex !== -1 && $dIndex !== -1 && $aIndex !== -1) {
                $table->filter('tr')->each(function ($tr) use (&$rawPlayers, $kIndex, $dIndex, $aIndex, $nameIndex, $agentIndex) {
                    $tds = $tr->filter('td');
                    if ($tds->count() > max($kIndex, $dIndex, $aIndex)) {
                        $nameNode = $tds->eq($nameIndex);
                        $player = $nameNode->filter('a')->count()
                            ? trim($nameNode->filter('a')->text())
                            : trim($nameNode->text());

                        if (!empty($player)) {
                            $agent = '';
                            if ($agentIndex !== -1 && $tds->eq($agentIndex)->count()) {
                                $agentNode = $tds->eq($agentIndex);
                                $img = $agentNode->filter('img');
                                $agent = $img->count()
                                    ? ($img->attr('alt') ?? $img->attr('title') ?? '')
                                    : trim($agentNode->text());
                            }

                            $rawPlayers[] = [
                                'name' => $player,
                                'kills' => (int) trim($tds->eq($kIndex)->text()),
                                'deaths' => (int) trim($tds->eq($dIndex)->text()),
                                'assists' => (int) trim($tds->eq($aIndex)->text()),
                                'agent' => $agent,
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

        // Valorant map scores
        $crawler->filter('div.vm-stats-game')->each(function (Crawler $node) use (&$details) {
            $mapHeader = $node->filter('.vm-stats-game-header');
            $mapName = $mapHeader->count() ? trim($mapHeader->text()) : 'Unknown Map';
            $mapName = preg_replace('/\s*\d+-\d+\s*$/', '', $mapName); // Remove score from name

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


        // New Liquipedia structure (match-bm)
        if (empty($details['maps'])) {
            $crawler->filter('div[data-toggle-area-content]')->each(function (Crawler $node) use (&$details) {
                // Determine if this area content is a map (usually has a map name inside)
                $mapNameNode = $node->filter('div.match-bm-lol-game-summary-map, div.match-bm-game-summary-map');
                if ($mapNameNode->count()) {
                    $mapName = trim($mapNameNode->text());
                    $scoreNode = $node->filter('div.match-bm-lol-game-summary-score, div.match-bm-game-summary-score');
                    $score = $scoreNode->count() ? trim($scoreNode->text()) : '';

                    if ($score) {
                        // Handle non-breaking hyphens or other separators
                        $score = str_replace(["\xe2\x80\x91", "&#8209;"], '-', $score);
                        $parts = explode('-', $score);

                        // Sometimes map name covers "Overall", skip it
                        if (stripos($mapName, 'Overall') === false) {
                            $details['maps'][] = [
                                'name' => $mapName,
                                'score1' => isset($parts[0]) ? trim($parts[0]) : '0',
                                'score2' => isset($parts[1]) ? trim($parts[1]) : '0'
                            ];
                        }
                    }
                }
            });
        }

        // Fallback for maps
        if (empty($details['maps'])) {
            $crawler->filter('div.match-history-game')->each(function (Crawler $node) use (&$details) {
                $mapNode = $node->filter('.match-history-map');
                $mapName = $mapNode->count() ? trim($mapNode->text()) : 'Unknown';
                $scoreNode = $node->filter('.match-history-score');
                $score = $scoreNode->count() ? $scoreNode->text() : '';

                if ($score) {
                    $score = str_replace(["\xe2\x80\x91", "&#8209;"], '-', $score);
                    $parts = explode('-', $score);
                    $details['maps'][] = [
                        'name' => $mapName,
                        'score1' => isset($parts[0]) ? trim($parts[0]) : '0',
                        'score2' => isset($parts[1]) ? trim($parts[1]) : '0'
                    ];
                }
            });
        }

        // Extract overall player stats
        $details['players'] = $this->extractPlayerStats($crawler);

        // Extract per-map stats
        $details['players_by_map'] = $this->extractPlayerStatsByMap($crawler);

        return $details;
    }
}
