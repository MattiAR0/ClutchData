<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="max-w-5xl mx-auto">
    <!-- Breadcrumb / Back -->
    <div class="mb-8">
        <a href="."
            class="inline-flex items-center text-zinc-500 hover:text-indigo-400 text-sm font-bold tracking-wider uppercase transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Match List
        </a>
    </div>

    <!-- Main Card -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-lg overflow-hidden relative shadow-2xl">
        <!-- Accent Line -->
        <div class="h-1 w-full bg-gradient-to-r from-indigo-500 via-purple-500 to-rose-500"></div>

        <div class="p-4 sm:p-6 md:p-8 lg:p-12">
            <!-- Header Info -->
            <div class="text-center mb-16 relative">
                <div
                    class="absolute top-0 left-1/2 -translate-x-1/2 w-64 h-32 bg-indigo-500/20 blur-[100px] rounded-full pointer-events-none">
                </div>

                <div class="relative z-10 flex flex-col items-center">
                    <div class="flex justify-center gap-2 mb-6">
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full bg-zinc-800/80 border border-zinc-700/50 text-[10px] text-zinc-400 font-bold uppercase tracking-widest backdrop-blur-sm shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-zinc-500 mr-2"></span>
                            <?= htmlspecialchars($match['game_type']) ?>
                        </span>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full bg-zinc-800/80 border border-zinc-700/50 text-[10px] text-zinc-400 font-bold uppercase tracking-widest backdrop-blur-sm shadow-sm">
                            <?= htmlspecialchars($match['match_region']) ?>
                        </span>
                    </div>

                    <h2
                        class="text-lg sm:text-xl md:text-2xl lg:text-3xl text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400 font-black uppercase tracking-wider mb-3 drop-shadow-sm text-center px-2">
                        <?= htmlspecialchars($match['tournament_name']) ?>
                    </h2>

                    <p class="text-zinc-500 font-mono text-sm tracking-wide">
                        <?= date('F j, Y • H:i', strtotime($match['match_time'])) ?> UTC
                    </p>
                </div>
            </div>

            <!-- Scoreboard -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:gap-12 mb-20 relative">
                <!-- Background Separator Effect -->
                <div
                    class="hidden md:block absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-px h-24 bg-gradient-to-b from-transparent via-zinc-800 to-transparent">
                </div>

                <!-- Team 1 -->
                <div class="text-center md:text-right flex-1 w-full group">
                    <div class="text-2xl sm:text-3xl md:text-4xl lg:text-6xl font-black text-white mb-2 break-words leading-tight tracking-tighter group-hover:text-indigo-400 transition-colors duration-300"
                        title="<?= htmlspecialchars($match['team1_name']) ?>">
                        <?= htmlspecialchars($match['team1_name']) ?>
                    </div>
                    <div
                        class="text-xs font-bold text-zinc-600 uppercase tracking-[0.2em] group-hover:text-zinc-500 transition-colors">
                        Team A</div>
                </div>

                <!-- Score / VS -->
                <div class="flex flex-col items-center justify-center shrink-0 mx-4 z-10 min-w-[140px]">
                    <?php if (($match['match_status'] ?? 'upcoming') === 'upcoming'): ?>
                        <div
                            class="w-20 h-20 rounded-full bg-zinc-900 flex items-center justify-center border-4 border-zinc-800 shadow-xl relative overflow-hidden group">
                            <div
                                class="absolute inset-0 bg-gradient-to-tr from-zinc-800 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                            </div>
                            <span
                                class="text-2xl font-black text-zinc-700 group-hover:text-zinc-500 transition-colors">VS</span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-1">
                            <span
                                class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-black text-white tracking-tighter tabular-nums drop-shadow-2xl">
                                <?= $match['team1_score'] ?>
                            </span>
                            <span
                                class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-bold text-zinc-500">:</span>
                            <span
                                class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-black text-white tracking-tighter tabular-nums drop-shadow-2xl">
                                <?= $match['team2_score'] ?>
                            </span>
                        </div>

                        <div class="mt-4">
                            <?php if ($match['match_status'] === 'live'): ?>
                                <span
                                    class="inline-flex items-center px-4 py-1.5 rounded bg-rose-500/10 text-rose-500 text-xs font-bold uppercase tracking-widest border border-rose-500/20 shadow-[0_0_15px_rgba(244,63,94,0.3)] animate-pulse">
                                    <span class="w-2 h-2 bg-rose-500 rounded-full inline-block mr-2 animate-ping"></span>
                                    LIVE MATCH
                                </span>
                            <?php else: ?>
                                <span
                                    class="inline-block px-4 py-1.5 rounded bg-zinc-800 text-zinc-400 text-[10px] font-bold uppercase tracking-[0.2em] border border-zinc-700 shadow-sm">
                                    Final Score
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Team 2 -->
                <div class="text-center md:text-left flex-1 w-full group">
                    <div class="text-2xl sm:text-3xl md:text-4xl lg:text-6xl font-black text-white mb-2 break-words leading-tight tracking-tighter group-hover:text-rose-400 transition-colors duration-300"
                        title="<?= htmlspecialchars($match['team2_name']) ?>">
                        <?= htmlspecialchars($match['team2_name']) ?>
                    </div>
                    <div
                        class="text-xs font-bold text-zinc-600 uppercase tracking-[0.2em] group-hover:text-zinc-500 transition-colors">
                        Team B</div>
                </div>
            </div>

            <!-- AI Prediction Bar -->
            <div class="mb-16 max-w-3xl mx-auto">
                <div class="flex items-center gap-4 mb-6">
                    <div class="h-px bg-zinc-800 flex-grow"></div>
                    <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-[0.2em] flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        AI Win Probability
                    </h3>
                    <div class="h-px bg-zinc-800 flex-grow"></div>
                </div>

                <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-lg relative overflow-hidden">
                    <!-- AI Source Badge -->
                    <?php
                    $aiSource = $match['ai_source'] ?? 'elo';
                    $isGemini = $aiSource === 'gemini';
                    ?>
                    <div class="absolute top-4 right-4">
                        <?php if ($isGemini): ?>
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full bg-gradient-to-r from-blue-500/20 to-purple-500/20 border border-blue-500/30 text-[10px] text-blue-400 font-bold uppercase tracking-widest backdrop-blur-sm shadow-lg">
                                <svg class="w-3 h-3 mr-1.5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                                </svg>
                                Gemini AI
                            </span>
                        <?php else: ?>
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full bg-zinc-800/80 border border-zinc-700/50 text-[10px] text-zinc-400 font-bold uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 rounded-full bg-zinc-500 mr-2"></span>
                                ELO Rating
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-between items-end mb-4 relative z-10">
                        <div class="text-left">
                            <span class="block text-[10px] text-zinc-500 uppercase tracking-widest mb-1 font-bold">Team
                                A</span>
                            <span
                                class="text-4xl font-black text-indigo-500 tabular-nums tracking-tighter drop-shadow-sm"><?= number_format($match['ai_prediction'] ?? 50, 1) ?>%</span>
                        </div>
                        <!-- V Divider -->
                        <div class="text-zinc-700 font-black text-xl self-center opacity-20">/</div>

                        <div class="text-right">
                            <span class="block text-[10px] text-zinc-500 uppercase tracking-widest mb-1 font-bold">Team
                                B</span>
                            <span
                                class="text-4xl font-black text-rose-500 tabular-nums tracking-tighter drop-shadow-sm"><?= number_format(100 - ($match['ai_prediction'] ?? 50), 1) ?>%</span>
                        </div>
                    </div>

                    <div class="h-3 bg-zinc-800 rounded-full overflow-hidden flex ring-1 ring-zinc-700/50">
                        <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600 shadow-[0_0_10px_rgba(99,102,241,0.5)] relative"
                            style="width: <?= $match['ai_prediction'] ?? 50 ?>%">
                            <div
                                class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0IiBoZWlnaHQ9IjQiPgo8cmVjdCB3aWR0aD0iNCIgaGVpZ2h0PSI0IiBmaWxsPSIjZmZmIiBmaWxsLW9wYWNpdHk9IjAuMSIvPgo8L3N2Zz4=')] opacity-30">
                            </div>
                        </div>
                        <div class="h-full bg-zinc-800 w-px"></div>
                        <div class="h-full bg-gradient-to-l from-rose-500 to-rose-600 shadow-[0_0_10px_rgba(244,63,94,0.5)] relative"
                            style="width: <?= 100 - ($match['ai_prediction'] ?? 50) ?>%">
                            <div
                                class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0IiBoZWlnaHQ9IjQiPgo8cmVjdCB3aWR0aD0iNCIgaGVpZ2h0PSI0IiBmaWxsPSIjZmZmIiBmaWxsLW9wYWNpdHk9IjAuMSIvPgo8L3N2Zz4=')] opacity-30">
                            </div>
                        </div>
                    </div>

                    <!-- AI Explanation -->
                    <div class="mt-4 text-center">
                        <?php if (!empty($match['ai_explanation']) && $isGemini): ?>
                            <div class="bg-zinc-800/50 rounded-lg p-4 border border-zinc-700/50">
                                <p class="text-sm text-zinc-300 leading-relaxed">
                                    <svg class="w-4 h-4 inline-block mr-1 text-blue-400 -mt-0.5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    <?= htmlspecialchars($match['ai_explanation']) ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <p class="text-[10px] text-zinc-600 font-mono">
                                Basado en rating ELO y estadísticas históricas
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Details List -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-16 border-y border-zinc-800 py-8 bg-zinc-900/30">
                <div class="text-center border-r border-zinc-800 last:border-0 md:last:border-0">
                    <span class="block text-zinc-500 text-[10px] uppercase tracking-widest mb-2 font-bold">Format</span>
                    <span class="text-zinc-200 font-mono font-bold"><?= $match['bo_type'] ?? 'Bo3' ?></span>
                </div>
                <div class="text-center border-r border-zinc-800 max-md:border-0 md:border-r">
                    <span class="block text-zinc-500 text-[10px] uppercase tracking-widest mb-2 font-bold">Source</span>
                    <span class="text-zinc-200 font-mono font-bold">Liquipedia</span>
                </div>
                <div class="text-center border-r border-zinc-800 max-md:border-t max-md:pt-4 md:border-t-0 md:pt-0">
                    <span
                        class="block text-zinc-500 text-[10px] uppercase tracking-widest mb-2 font-bold">Updated</span>
                    <span
                        class="text-zinc-200 font-mono font-bold text-xs"><?= date('H:i', strtotime($match['created_at'])) ?>
                        UTC</span>
                </div>
                <div class="text-center max-md:border-t max-md:pt-4 md:border-t-0 md:pt-0">
                    <span class="block text-zinc-500 text-[10px] uppercase tracking-widest mb-2 font-bold">Status</span>
                    <span
                        class="inline-block px-2 py-0.5 rounded bg-zinc-800 text-zinc-400 text-[10px] font-bold uppercase tracking-wider border border-zinc-700 capitalize">
                        <?= $match['match_status'] ?>
                    </span>
                </div>
            </div>

            <!-- Detailed Stats / Maps -->
            <?php if (!empty($match['details_decoded']['maps'])): ?>
                <div class="mb-12">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="h-px bg-zinc-800 flex-grow"></div>
                        <h3 class="text-lg font-bold text-white uppercase tracking-[0.2em]">Map Breakdown</h3>
                        <div class="h-px bg-zinc-800 flex-grow"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($match['details_decoded']['maps'] as $index => $map): ?>
                            <div
                                class="bg-zinc-900 border border-zinc-800 rounded-lg p-5 flex flex-col items-center justify-center relative overflow-hidden group hover:border-zinc-700 transition-colors">
                                <div
                                    class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-purple-500/5 opacity-0 group-hover:opacity-100 transition-opacity">
                                </div>
                                <span class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-3">Map
                                    <?= $index + 1 ?></span>
                                <span
                                    class="font-black text-white font-mono text-2xl mb-4 tracking-tight"><?= htmlspecialchars($map['name']) ?></span>

                                <div
                                    class="flex items-center gap-6 bg-zinc-950/50 px-6 py-2 rounded-full border border-zinc-800/50">
                                    <span
                                        class="text-2xl font-bold <?= $map['score1'] > $map['score2'] ? 'text-indigo-400' : 'text-zinc-500' ?>">
                                        <?= htmlspecialchars($map['score1']) ?>
                                    </span>
                                    <span class="text-zinc-700 font-black">-</span>
                                    <span
                                        class="text-2xl font-bold <?= $map['score2'] > $map['score1'] ? 'text-rose-400' : 'text-zinc-500' ?>">
                                        <?= htmlspecialchars($map['score2']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Unified Player Statistics (VLR + HLTV + Liquipedia) -->
            <?php
            $hasStats = !empty($match['merged_stats']) &&
                (count($match['merged_stats'][$match['team1_name']] ?? []) > 0 ||
                    count($match['merged_stats'][$match['team2_name']] ?? []) > 0);

            if ($hasStats):
                // Determine data source for header display
                $hasVlrData = false;
                $hasHltvData = false;
                $hasLiquipediaData = false;
                foreach ($match['merged_stats'] as $teamPlayers) {
                    foreach ($teamPlayers as $p) {
                        if ($p['data_source'] === 'vlr')
                            $hasVlrData = true;
                        if ($p['data_source'] === 'hltv')
                            $hasHltvData = true;
                        if ($p['data_source'] === 'liquipedia')
                            $hasLiquipediaData = true;
                    }
                }
                $hasAdvancedStats = $hasVlrData || $hasHltvData;
                ?>
                <div class="mb-16">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="h-px bg-zinc-800 flex-grow"></div>
                        <h3 class="text-lg font-bold text-white uppercase tracking-[0.2em] flex items-center gap-3">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Player Statistics
                        </h3>
                        <div class="h-px bg-zinc-800 flex-grow"></div>
                    </div>

                    <!-- Data Source Badge -->
                    <div class="flex justify-center gap-2 mb-6">
                        <?php if ($hasVlrData): ?>
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-[10px] text-rose-400 font-bold uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-2 animate-pulse"></span>
                                VLR.gg Stats
                            </span>
                        <?php endif; ?>
                        <?php if ($hasHltvData): ?>
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full bg-orange-500/10 border border-orange-500/30 text-[10px] text-orange-400 font-bold uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 mr-2 animate-pulse"></span>
                                HLTV Stats
                            </span>
                        <?php endif; ?>
                        <?php if ($hasLiquipediaData && !$hasAdvancedStats): ?>
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-[10px] text-blue-400 font-bold uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-2"></span>
                                Liquipedia Stats
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                        <?php
                        $teams = [
                            ['name' => $match['team1_name'], 'players' => $match['merged_stats'][$match['team1_name']] ?? [], 'color' => 'indigo'],
                            ['name' => $match['team2_name'], 'players' => $match['merged_stats'][$match['team2_name']] ?? [], 'color' => 'rose']
                        ];

                        foreach ($teams as $team):
                            if (empty($team['players']))
                                continue;
                            ?>
                            <div
                                class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden shadow-xl ring-1 ring-white/5">
                                <!-- Team Header -->
                                <div
                                    class="px-6 py-4 bg-gradient-to-r from-<?= $team['color'] ?>-500/10 to-transparent border-b border-zinc-800 flex items-center justify-between">
                                    <h4 class="text-lg font-black text-white italic tracking-wide">
                                        <?= htmlspecialchars($team['name']) ?>
                                    </h4>
                                    <span class="text-xs font-mono text-zinc-500 uppercase">
                                        <?= count($team['players']) ?> Players
                                    </span>
                                </div>

                                <!-- Table -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left">
                                        <thead>
                                            <tr class="border-b border-zinc-800 bg-zinc-900/80">
                                                <th
                                                    class="px-4 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest">
                                                    Player</th>
                                                <?php if ($hasHltvData): ?>
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center"
                                                        title="HLTV Rating 2.0">Rating</th>
                                                <?php elseif ($hasVlrData): ?>
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center"
                                                        title="Average Combat Score">ACS</th>
                                                <?php endif; ?>
                                                <th
                                                    class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">
                                                    K</th>
                                                <th
                                                    class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">
                                                    D</th>
                                                <th
                                                    class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">
                                                    A</th>
                                                <th
                                                    class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">
                                                    K/D</th>
                                                <?php if ($hasAdvancedStats): ?>
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center"
                                                        title="Average Damage per Round">ADR</th>
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center"
                                                        title="Kill/Assist/Survive/Trade %">KAST</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-800/50">
                                            <?php foreach ($team['players'] as $player): ?>
                                                <?php
                                                $kd = $player['deaths'] > 0 ? $player['kills'] / $player['deaths'] : $player['kills'];
                                                $kdColor = $kd >= 1.0 ? 'text-emerald-400' : 'text-rose-400';
                                                if ($player['kills'] == 0 && $player['deaths'] == 0)
                                                    $kdColor = 'text-zinc-500';

                                                // Rating color (for HLTV)
                                                $rating = $player['rating'] ?? null;
                                                $ratingColor = $rating !== null ? ($rating >= 1.10 ? 'text-emerald-400' : ($rating >= 1.0 ? 'text-yellow-400' : 'text-zinc-400')) : 'text-zinc-600';

                                                // ACS color (for VLR)
                                                $acs = $player['acs'] ?? null;
                                                $acsColor = $acs !== null ? ($acs >= 250 ? 'text-emerald-400' : ($acs >= 200 ? 'text-yellow-400' : 'text-zinc-400')) : 'text-zinc-600';

                                                $kast = $player['kast'] ?? null;
                                                $kastColor = $kast !== null ? ($kast >= 75 ? 'text-emerald-400' : ($kast >= 60 ? 'text-yellow-400' : 'text-zinc-400')) : 'text-zinc-600';

                                                ?>
                                                <tr class="group hover:bg-white/[0.02] transition-colors">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-2">
                                                            <?php if (!empty($player['agent'])): ?>
                                                                <span
                                                                    class="text-[10px] text-zinc-500 bg-zinc-800 px-1.5 py-0.5 rounded"><?= htmlspecialchars($player['agent']) ?></span>
                                                            <?php endif; ?>
                                                            <span
                                                                class="font-bold text-zinc-200 text-sm"><?= htmlspecialchars($player['name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <?php if ($hasHltvData): ?>
                                                        <td
                                                            class="px-3 py-3 text-center font-mono text-sm font-bold <?= $ratingColor ?>">
                                                            <?= $rating !== null ? number_format($rating, 2) : '-' ?>
                                                        </td>
                                                    <?php elseif ($hasVlrData): ?>
                                                        <td class="px-3 py-3 text-center font-mono text-sm font-bold <?= $acsColor ?>">
                                                            <?= $acs !== null ? $acs : '-' ?>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td class="px-3 py-3 text-center font-mono text-sm text-zinc-300 font-medium">
                                                        <?= htmlspecialchars($player['kills'] ?? 0) ?>
                                                    </td>
                                                    <td class="px-3 py-3 text-center font-mono text-sm text-zinc-400">
                                                        <?= htmlspecialchars($player['deaths'] ?? 0) ?>
                                                    </td>
                                                    <td class="px-3 py-3 text-center font-mono text-sm text-zinc-400">
                                                        <?= htmlspecialchars($player['assists'] ?? 0) ?>
                                                    </td>
                                                    <td class="px-3 py-3 text-center font-mono text-sm font-bold <?= $kdColor ?>">
                                                        <?= number_format($kd, 2) ?>
                                                    </td>
                                                    <?php if ($hasAdvancedStats): ?>
                                                        <td class="px-3 py-3 text-center font-mono text-sm text-zinc-300">
                                                            <?= $player['adr'] !== null ? number_format($player['adr'], 1) : '-' ?>
                                                        </td>
                                                        <td class="px-3 py-3 text-center font-mono text-sm <?= $kastColor ?>">
                                                            <?= $kast !== null ? number_format($kast, 1) . '%' : '-' ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Async Loading Placeholder for Stats (only if no cached stats exist) -->
            <?php if (!empty($match['needs_async_stats']) && !$hasStats): ?>
                <div id="async-stats-container" class="mb-16">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="h-px bg-zinc-800 flex-grow"></div>
                        <h3 class="text-lg font-bold text-white uppercase tracking-[0.2em] flex items-center gap-3">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Player Statistics
                        </h3>
                        <div class="h-px bg-zinc-800 flex-grow"></div>
                    </div>

                    <!-- Loading State -->
                    <div id="stats-loading" class="text-center py-12">
                        <div class="inline-flex flex-col items-center gap-4">
                            <div class="relative">
                                <div
                                    class="w-12 h-12 border-4 border-zinc-700 border-t-indigo-500 rounded-full animate-spin">
                                </div>
                            </div>
                            <p class="text-zinc-500 text-sm font-mono">Loading advanced statistics...</p>
                            <p class="text-zinc-600 text-xs">
                                <?= $match['game_type'] === 'valorant' ? 'Fetching from VLR.gg' : 'Fetching from HLTV' ?>
                            </p>
                        </div>
                    </div>

                    <!-- Stats Content (replaced by JavaScript) -->
                    <div id="stats-content" class="hidden"></div>
                </div>

                <script>
                    (function () {
                        const matchId = <?= (int) $match['id'] ?>;
                        const gameType = '<?= htmlspecialchars($match['game_type']) ?>';
                        const team1Name = '<?= htmlspecialchars(addslashes($match['team1_name'])) ?>';
                        const team2Name = '<?= htmlspecialchars(addslashes($match['team2_name'])) ?>';

                        const loadingEl = document.getElementById('stats-loading');
                        const contentEl = document.getElementById('stats-content');

                        let availableMaps = [];
                        let currentMap = 'overall';

                        fetch(`./api/match/stats?id=${matchId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.stats && Object.keys(data.stats).length > 0) {
                                    availableMaps = data.available_maps || [];
                                    currentMap = data.current_map || 'overall';
                                    renderStats(data.stats, data.source, availableMaps, currentMap);
                                } else {
                                    loadingEl.innerHTML = `
                                    <div class="text-center py-8">
                                        <p class="text-zinc-500 text-sm">No detailed statistics available for this match.</p>
                                    </div>
                                `;
                                }
                            })
                            .catch(error => {
                                console.error('Error loading stats:', error);
                                loadingEl.innerHTML = `
                                <div class="text-center py-8">
                                    <p class="text-zinc-500 text-sm">Failed to load statistics. Please refresh the page.</p>
                                </div>
                            `;
                            });

                        function loadMapStats(mapName) {
                            currentMap = mapName;
                            contentEl.innerHTML = '<div class="text-center py-8"><div class="w-8 h-8 border-4 border-zinc-700 border-t-indigo-500 rounded-full animate-spin mx-auto"></div></div>';

                            fetch(`./api/match/stats?id=${matchId}&map=${encodeURIComponent(mapName)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.stats) {
                                        renderStats(data.stats, data.source, availableMaps, mapName);
                                    }
                                })
                                .catch(console.error);
                        }

                        function renderStats(stats, source, maps, activeMap) {
                            const sourceBadge = source === 'vlr'
                                ? '<span class="inline-flex items-center px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-[10px] text-rose-400 font-bold uppercase tracking-widest"><span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-2 animate-pulse"></span>VLR.gg Stats</span>'
                                : source === 'hltv'
                                    ? '<span class="inline-flex items-center px-3 py-1 rounded-full bg-orange-500/10 border border-orange-500/30 text-[10px] text-orange-400 font-bold uppercase tracking-widest"><span class="w-1.5 h-1.5 rounded-full bg-orange-500 mr-2 animate-pulse"></span>HLTV Stats</span>'
                                    : '<span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-[10px] text-blue-400 font-bold uppercase tracking-widest"><span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-2"></span>Liquipedia Stats</span>';

                            const hasAdvanced = source === 'vlr' || source === 'hltv';
                            const isHltv = source === 'hltv';

                            // Map tabs (only show if more than just 'overall')
                            let tabsHtml = '';
                            if (maps && maps.length > 1) {
                                tabsHtml = '<div class="flex flex-wrap justify-center gap-2 mb-6">';
                                maps.forEach(map => {
                                    const isActive = map === activeMap;
                                    const activeClass = isActive
                                        ? 'bg-indigo-600 text-white border-indigo-500'
                                        : 'bg-zinc-800/50 text-zinc-400 border-zinc-700 hover:bg-zinc-700 hover:text-white';
                                    const label = map === 'overall' ? 'All Maps' : map;
                                    tabsHtml += `<button onclick="window.loadMapStats('${escapeHtml(map)}')" class="px-4 py-2 text-xs font-bold uppercase tracking-wider border rounded-lg transition-all ${activeClass}">${escapeHtml(label)}</button>`;
                                });
                                tabsHtml += '</div>';
                            }

                            let html = `<div class="flex justify-center gap-2 mb-4">${sourceBadge}</div>`;
                            html += tabsHtml;
                            html += '<div class="grid grid-cols-1 xl:grid-cols-2 gap-8">';

                            const teams = [
                                { name: team1Name, players: stats.team1 || [], color: 'indigo' },
                                { name: team2Name, players: stats.team2 || [], color: 'rose' }
                            ];

                            teams.forEach(team => {
                                if (!team.players || team.players.length === 0) return;

                                html += `
                                <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden shadow-xl ring-1 ring-white/5">
                                    <div class="px-6 py-4 bg-gradient-to-r from-${team.color}-500/10 to-transparent border-b border-zinc-800 flex items-center justify-between">
                                        <h4 class="text-lg font-black text-white italic tracking-wide">${escapeHtml(team.name)}</h4>
                                        <span class="text-xs font-mono text-zinc-500 uppercase">${team.players.length} Players</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left">
                                            <thead>
                                                <tr class="border-b border-zinc-800 bg-zinc-900/80">
                                                    <th class="px-4 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest">Player</th>
                                                    ${isHltv ? '<th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">Rating</th>'
                                    : hasAdvanced ? '<th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">ACS</th>' : ''}
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">K</th>
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">D</th>
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">A</th>
                                                    <th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">K/D</th>
                                                    ${hasAdvanced ? '<th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">ADR</th><th class="px-3 py-3 text-[10px] font-bold text-zinc-500 uppercase tracking-widest text-center">KAST</th>' : ''}
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-800/50">
                                                ${team.players.map(p => renderPlayerRow(p, hasAdvanced, isHltv)).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            `;
                            });

                            html += '</div>';

                            loadingEl.classList.add('hidden');
                            contentEl.innerHTML = html;
                            contentEl.classList.remove('hidden');
                        }

                        // Expose loadMapStats to global scope for button onclick
                        window.loadMapStats = loadMapStats;

                        function renderPlayerRow(player, hasAdvanced, isHltv) {
                            const kills = player.kills || 0;
                            const deaths = player.deaths || 0;
                            const kd = deaths > 0 ? kills / deaths : kills;
                            const kdColor = kd >= 1.0 ? 'text-emerald-400' : 'text-rose-400';

                            const rating = player.rating;
                            const ratingColor = rating !== null ? (rating >= 1.10 ? 'text-emerald-400' : (rating >= 1.0 ? 'text-yellow-400' : 'text-zinc-400')) : 'text-zinc-600';

                            const acs = player.acs;
                            const acsColor = acs !== null ? (acs >= 250 ? 'text-emerald-400' : (acs >= 200 ? 'text-yellow-400' : 'text-zinc-400')) : 'text-zinc-600';

                            const kast = player.kast;
                            const kastColor = kast !== null ? (kast >= 75 ? 'text-emerald-400' : (kast >= 60 ? 'text-yellow-400' : 'text-zinc-400')) : 'text-zinc-600';

                            const playerName = player.player_name || player.name || 'Unknown';
                            const agent = player.agent;

                            return `
                            <tr class="group hover:bg-white/[0.02] transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        ${agent ? `<span class="text-[10px] text-zinc-500 bg-zinc-800 px-1.5 py-0.5 rounded">${escapeHtml(agent)}</span>` : ''}
                                        <span class="font-bold text-zinc-200 text-sm">${escapeHtml(playerName)}</span>
                                    </div>
                                </td>
                                ${isHltv ? `<td class="px-3 py-3 text-center font-mono text-sm font-bold ${ratingColor}">${rating !== null ? rating.toFixed(2) : '-'}</td>`
                                : hasAdvanced ? `<td class="px-3 py-3 text-center font-mono text-sm font-bold ${acsColor}">${acs !== null ? acs : '-'}</td>` : ''}
                                <td class="px-3 py-3 text-center font-mono text-sm text-zinc-300 font-medium">${kills}</td>
                                <td class="px-3 py-3 text-center font-mono text-sm text-zinc-400">${deaths}</td>
                                <td class="px-3 py-3 text-center font-mono text-sm text-zinc-400">${player.assists || 0}</td>
                                <td class="px-3 py-3 text-center font-mono text-sm font-bold ${kdColor}">${kd.toFixed(2)}</td>
                                ${hasAdvanced ? `
                                    <td class="px-3 py-3 text-center font-mono text-sm text-zinc-300">${player.adr !== null ? player.adr.toFixed(1) : '-'}</td>
                                    <td class="px-3 py-3 text-center font-mono text-sm ${kastColor}">${kast !== null ? kast.toFixed(1) + '%' : '-'}</td>
                                ` : ''}
                            </tr>
                        `;
                        }

                        function escapeHtml(text) {
                            const div = document.createElement('div');
                            div.textContent = text;
                            return div.innerHTML;
                        }
                    })();
                </script>
            <?php endif; ?>

            <!-- External Link -->
            <?php if (!empty($match['match_url'])): ?>
                <div class="text-center">
                    <a href="<?= htmlspecialchars($match['match_url']) ?>" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center px-8 py-4 bg-zinc-800 hover:bg-zinc-700 text-white font-bold rounded-sm border border-zinc-700 transition-all uppercase tracking-widest text-xs group hover:border-indigo-500/50">
                        View Full Stats on Liquipedia
                        <svg class="w-3 h-3 ml-2 opacity-50 group-hover:opacity-100 transition-opacity" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>