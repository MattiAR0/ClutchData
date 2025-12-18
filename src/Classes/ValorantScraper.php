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



    protected function extractPlayerStats(Crawler $crawler): array
    {
        $rawPlayers = [];

        // 1. Try new div-based structure (Modern Liquipedia Valorant Match Pages)
        // We prioritize this as it is cleaner when available.
        // Scope to the FIRST wrapper to avoid double counting (Matches often have Total + Map 1 + Map 2 duplicates)
        $wrapper = $crawler->filter('.match-bm-players-wrapper')->first();

        if ($wrapper->count() > 0) {
            // Updated logic: Iterate over teams first to get team context
            $wrapper->filter('.match-bm-players-team')->each(function (Crawler $teamNode) use (&$rawPlayers) {
                // Extract Team Name
                $teamName = 'Unknown Team';
                $header = $teamNode->filter('.match-bm-players-team-header');
                if ($header->count()) {
                    // Try text first
                    $text = trim($header->text());
                    if (!empty($text)) {
                        $teamName = $text;
                    } else {
                        // Try image alt or title
                        $img = $header->filter('img')->last(); // Last image often contains the logo if multiple
                        if ($img->count()) {
                            $teamName = $img->attr('alt') ?? $img->attr('title') ?? 'Unknown Team';
                        }

                        // Fallback to link title
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
                    // Prefer name from link to avoid extra text
                    if ($nameNode->filter('a')->count()) {
                        $player = trim($nameNode->filter('a')->text());
                    } else {
                        $player = $nameNode->count() ? trim($nameNode->text()) : 'Unknown';
                    }

                    // Collect Agents
                    $agent = '';
                    $agentImg = $nameNode->filter('img')->last();
                    if ($agentImg->count()) {
                        $agent = $agentImg->attr('alt') ?? $agentImg->attr('title') ?? '';
                    }

                    $k = 0;
                    $d = 0;
                    $a = 0;

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
                            'team' => $teamName
                        ];
                    }
                });
            });
        }

        // 2. If Divs returned nothing, Fallback to standard wikitable
        if (empty($rawPlayers)) {
            $crawler->filter('table.wikitable, table.vm-stats-game-table')->each(function (Crawler $table) use (&$rawPlayers) {
                $headers = $table->filter('th')->each(function ($th) {
                    return trim($th->text());
                });

                $kIndex = -1;
                $dIndex = -1;
                $aIndex = -1;
                $agentIndex = -1;
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
                            // Prefer link text
                            if ($nameNode->filter('a')->count()) {
                                $player = trim($nameNode->filter('a')->text());
                            } else {
                                $player = trim($nameNode->text());
                            }

                            if (!empty($player)) {
                                $agent = '';
                                if ($agentIndex !== -1 && $tds->eq($agentIndex)->count()) {
                                    $agentNode = $tds->eq($agentIndex);
                                    $img = $agentNode->filter('img');
                                    if ($img->count()) {
                                        $agent = $img->attr('alt') ?? $img->attr('title') ?? '';
                                    } else {
                                        $agent = trim($agentNode->text());
                                    }
                                }

                                $rawPlayers[] = [
                                    'name' => $player,
                                    'kills' => (int) trim($tds->eq($kIndex)->text()),
                                    'deaths' => (int) trim($tds->eq($dIndex)->text()),
                                    'assists' => (int) trim($tds->eq($aIndex)->text()),
                                    'agent' => $agent
                                ];
                            }
                        }
                    });
                }
            });
        }

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
                    'agents' => [],
                    'team' => $p['team'] ?? 'Unknown'
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
                'team' => $p['team']
            ];
        }

        return $finalPlayers;
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
            'streams' => [],
            'players' => []
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

        $details['players'] = $this->extractPlayerStats($crawler);

        return $details;
    }
}
