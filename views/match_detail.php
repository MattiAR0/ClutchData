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

        <div class="p-8 md:p-12">
            <!-- Header Info -->
            <div class="text-center mb-12">
                <div class="flex justify-center gap-2 mb-4">
                    <span
                        class="inline-block px-3 py-1 rounded-full bg-zinc-800 border border-zinc-700 text-xs text-zinc-400 font-mono uppercase">
                        <?= htmlspecialchars($match['game_type']) ?>
                    </span>
                    <span
                        class="inline-block px-3 py-1 rounded-full bg-zinc-800 border border-zinc-700 text-xs text-zinc-400 font-mono uppercase">
                        <?= htmlspecialchars($match['match_region']) ?>
                    </span>
                </div>

                <h2 class="text-lg text-indigo-400 font-bold uppercase tracking-widest mb-2">
                    <?= htmlspecialchars($match['tournament_name']) ?>
                </h2>

                <p class="text-zinc-500 font-mono text-sm">
                    <?= date('F j, Y • H:i', strtotime($match['match_time'])) ?> UTC
                </p>
            </div>

            <!-- Scoreboard -->
            <div class="flex flex-col md:flex-row items-center justify-center gap-8 md:gap-12 mb-16">
                <!-- Team 1 -->
                <div class="text-center flex-1 w-full">
                    <div class="text-3xl md:text-5xl font-black text-white mb-2 break-words"
                        title="<?= htmlspecialchars($match['team1_name']) ?>">
                        <?= htmlspecialchars($match['team1_name']) ?>
                    </div>
                </div>

                <!-- VS / Score -->
                <div class="flex flex-col items-center justify-center shrink-0 mx-4">
                    <?php if (($match['match_status'] ?? 'upcoming') === 'upcoming'): ?>
                        <div
                            class="w-16 h-16 rounded-full bg-zinc-800 flex items-center justify-center border border-zinc-700 mb-2">
                            <span class="text-xl font-black text-zinc-500">VS</span>
                        </div>
                    <?php else: ?>
                        <div class="text-6xl font-black text-white tracking-tighter tabular-nums">
                            <?= $match['team1_score'] ?> <span class="text-zinc-700 mx-2">:</span>
                            <?= $match['team2_score'] ?>
                        </div>
                        <span
                            class="mt-4 px-3 py-1 rounded bg-zinc-800 text-white text-xs font-bold uppercase tracking-wider border border-zinc-700">
                            <?php if ($match['match_status'] === 'live'): ?>
                                <span class="w-2 h-2 bg-rose-500 rounded-full inline-block mr-2 animate-pulse"></span> LIVE
                            <?php else: ?>
                                FINAL
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Team 2 -->
                <div class="text-center flex-1 w-full">
                    <div class="text-3xl md:text-5xl font-black text-white mb-2 break-words"
                        title="<?= htmlspecialchars($match['team2_name']) ?>">
                        <?= htmlspecialchars($match['team2_name']) ?>
                    </div>
                </div>
            </div>

            <!-- AI Prediction Bar -->
            <div class="mb-12 max-w-2xl mx-auto">
                <div class="flex justify-between items-end mb-2">
                    <div class="text-left">
                        <span class="block text-[10px] text-zinc-500 uppercase tracking-widest mb-1">Win
                            Probability</span>
                        <span
                            class="text-2xl font-black text-indigo-500"><?= number_format($match['ai_prediction'], 1) ?>%</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-[10px] text-zinc-500 uppercase tracking-widest mb-1">Win
                            Probability</span>
                        <span
                            class="text-2xl font-black text-rose-500"><?= number_format(100 - $match['ai_prediction'], 1) ?>%</span>
                    </div>
                </div>

                <div class="h-2 bg-zinc-800 rounded-full overflow-hidden flex">
                    <div class="h-full bg-indigo-500" style="width: <?= $match['ai_prediction'] ?>%"></div>
                    <div class="h-full bg-rose-500" style="width: <?= 100 - $match['ai_prediction'] ?>%"></div>
                </div>
            </div>

            <!-- Details List -->
            <div class="bg-zinc-900/50 rounded border border-zinc-800/50 p-6 max-w-2xl mx-auto mb-8">
                <h3
                    class="text-xs font-bold text-zinc-500 uppercase tracking-widest mb-4 border-b border-zinc-800 pb-2">
                    Match Details</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="block text-zinc-600 text-xs mb-1">Format</span>
                        <span class="text-zinc-300 font-mono"><?= $match['bo_type'] ?? 'Best of 3 (Est.)' ?></span>
                    </div>
                    <div>
                        <span class="block text-zinc-600 text-xs mb-1">Data Source</span>
                        <span class="text-zinc-300 font-mono">Liquipedia Scraper</span>
                    </div>
                    <div>
                        <span class="block text-zinc-600 text-xs mb-1">Last Updated</span>
                        <span class="text-zinc-300 font-mono"><?= $match['created_at'] ?></span>
                    </div>
                    <div>
                        <span class="block text-zinc-600 text-xs mb-1">Status</span>
                        <span class="text-zinc-300 font-mono capitalize"><?= $match['match_status'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Detailed Stats / Maps -->
            <?php if (!empty($match['details_decoded']['maps'])): ?>
                <div class="mb-12">
                    <h3 class="text-center text-lg font-bold text-white uppercase tracking-widest mb-6">Map Breakdown</h3>
                    <div class="grid gap-4 max-w-2xl mx-auto">
                        <?php foreach ($match['details_decoded']['maps'] as $map): ?>
                            <div
                                class="bg-zinc-800/40 border border-zinc-700/50 p-4 rounded-sm flex items-center justify-between">
                                <span
                                    class="font-bold text-zinc-300 font-mono text-lg"><?= htmlspecialchars($map['name']) ?></span>
                                <div class="flex items-center gap-4">
                                    <span
                                        class="text-indigo-400 font-bold text-xl"><?= htmlspecialchars($map['score1']) ?></span>
                                    <span class="text-zinc-600">-</span>
                                    <span class="text-rose-400 font-bold text-xl"><?= htmlspecialchars($map['score2']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Player Statistics -->
            <?php if (!empty($match['details_decoded']['players'])): ?>
                <div class="mb-12">
                    <h3 class="text-center text-lg font-bold text-white uppercase tracking-widest mb-6">Player Statistics
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-zinc-400">
                            <thead class="text-xs text-zinc-500 uppercase bg-zinc-800/50 border-b border-zinc-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-bold tracking-wider">Player</th>
                                    <th scope="col" class="px-6 py-3 font-bold tracking-wider text-center">Agent/Champ</th>
                                    <th scope="col" class="px-6 py-3 font-bold tracking-wider text-center">K</th>
                                    <th scope="col" class="px-6 py-3 font-bold tracking-wider text-center">D</th>
                                    <th scope="col" class="px-6 py-3 font-bold tracking-wider text-center">A</th>
                                    <th scope="col" class="px-6 py-3 font-bold tracking-wider text-center">K/D</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800">
                                <?php foreach ($match['details_decoded']['players'] as $player): ?>
                                    <tr class="bg-zinc-900/50 hover:bg-zinc-800/50 transition-colors">
                                        <td class="px-6 py-4 font-medium text-white whitespace-nowrap">
                                            <?= htmlspecialchars($player['name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if (!empty($player['agent'])): ?>
                                                <span
                                                    class="inline-block px-2 py-1 bg-zinc-800 rounded text-xs text-zinc-300 border border-zinc-700">
                                                    <?= htmlspecialchars($player['agent']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-zinc-600">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center font-mono text-indigo-400 font-bold">
                                            <?= htmlspecialchars($player['kills']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center font-mono text-rose-400 font-bold">
                                            <?= htmlspecialchars($player['deaths']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center font-mono text-zinc-300">
                                            <?= htmlspecialchars($player['assists']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-center font-mono text-zinc-500">
                                            <?php
                                            $kd = $player['deaths'] > 0 ? $player['kills'] / $player['deaths'] : $player['kills'];
                                            echo number_format($kd, 2);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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