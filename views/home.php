<?php require __DIR__ . '/layouts/header.php'; ?>

<!-- Control Panel -->
<div class="mb-8 border-b border-zinc-800 pb-6">
    <div class="flex flex-col md:flex-row justify-between items-end">
        <div>
            <h2 class="text-3xl font-bold text-white tracking-tight">LIVE FEED</h2>
            <p class="text-zinc-500 mt-1 text-sm">Real-time match data aggregation.</p>

            <?php if (isset($message)): ?>
                <p class="text-emerald-500 text-sm mt-2 font-mono flex items-center">
                    <span class="mr-2">●</span> <?= htmlspecialchars($message) ?>
                </p>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <p class="text-red-500 text-sm mt-2 font-mono flex items-center">
                    <span class="mr-2">✕</span> <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="mt-4 md:mt-0 flex space-x-3">
            <a href="reset"
                class="group relative inline-flex items-center px-4 py-3 overflow-hidden font-medium text-rose-500 transition duration-300 ease-out border-2 border-rose-600 rounded shadow-md">
                <span
                    class="absolute inset-0 flex items-center justify-center w-full h-full text-white duration-300 -translate-x-full bg-rose-600 group-hover:translate-x-0 ease">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                        </path>
                    </svg>
                </span>
                <span
                    class="absolute flex items-center justify-center w-full h-full text-rose-500 transition-all duration-300 transform group-hover:translate-x-full ease">RESET</span>
                <span class="relative invisible">RESET</span>
            </a>

            <a href="scrape"
                class="group relative inline-flex items-center px-8 py-3 overflow-hidden font-medium text-indigo-600 transition duration-300 ease-out border-2 border-indigo-600 rounded shadow-md">
                <span
                    class="absolute inset-0 flex items-center justify-center w-full h-full text-white duration-300 -translate-x-full bg-indigo-600 group-hover:translate-x-0 ease">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </span>
                <span
                    class="absolute flex items-center justify-center w-full h-full text-indigo-500 transition-all duration-300 transform group-hover:translate-x-full ease">RUN
                    SYNC</span>
                <span class="relative invisible">RUN SYNC</span>
            </a>
        </div>
    </div>
</div>

<!-- Matches Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (empty($matches)): ?>
        <div class="col-span-full py-16 text-center border border-zinc-800 rounded bg-zinc-900/50">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-zinc-800 mb-4">
                <svg class="w-8 h-8 text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                    </path>
                </svg>
            </div>
            <h3 class="text-zinc-300 font-medium">No active matches found</h3>
            <p class="text-zinc-500 text-sm mt-1">Initialize scraping to populate the feed.</p>
        </div>
    <?php else: ?>
        <?php foreach ($matches as $match): ?>
            <?php
            // Minimalist indicators instead of full colors
            $accentColor = match ($match['game_type']) {
                'valorant' => 'bg-rose-500',
                'lol' => 'bg-sky-500',
                'cs2' => 'bg-amber-500',
                default => 'bg-zinc-500'
            };
            ?>
            <div
                class="group relative bg-zinc-900 border border-zinc-800 hover:border-zinc-700 hover:bg-zinc-800/80 transition-all duration-300 rounded-sm">
                <!-- Game Indicator Strip -->
                <div
                    class="absolute left-0 top-0 bottom-0 w-1 <?= $accentColor ?> rounded-l-sm opacity-75 group-hover:opacity-100">
                </div>

                <div class="p-5 pl-7">
                    <!-- Header -->
                    <div class="flex justify-between items-start mb-6">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-zinc-500 uppercase">
                            <?= $match['game_type'] ?>
                        </span>
                        <span class="text-xs font-mono text-zinc-400 bg-zinc-950 px-2 py-1 rounded">
                            <?= date('H:i', strtotime($match['match_time'])) ?> UTC
                        </span>
                    </div>

                    <!-- Teams -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-5/12">
                            <h3 class="font-bold text-lg text-white truncate group-hover:text-indigo-400 transition-colors">
                                <?= htmlspecialchars($match['team1_name']) ?>
                            </h3>
                        </div>
                        <div class="text-center w-2/12">
                            <span class="text-zinc-700 text-xs tracking-widest">VS</span>
                        </div>
                        <div class="w-5/12 text-right">
                            <h3 class="font-bold text-lg text-white truncate group-hover:text-indigo-400 transition-colors">
                                <?= htmlspecialchars($match['team2_name']) ?>
                            </h3>
                        </div>
                    </div>

                    <!-- Footer / Stats -->
                    <div class="flex justify-between items-end border-t border-zinc-800 pt-4 mt-2">
                        <div class="flex flex-col">
                            <span class="text-[10px] text-zinc-600 uppercase tracking-wider mb-1">League</span>
                            <span class="text-xs text-zinc-300 truncate max-w-[140px]"
                                title="<?= htmlspecialchars($match['tournament_name']) ?>">
                                <?= htmlspecialchars($match['tournament_name']) ?>
                            </span>
                        </div>

                        <div class="text-right">
                            <span class="text-[10px] text-zinc-600 uppercase tracking-wider block mb-1">Win Prob</span>
                            <span class="text-lg font-mono font-bold text-white">
                                <?= number_format($match['ai_prediction'], 0) ?><span class="text-indigo-500 text-sm">%</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>