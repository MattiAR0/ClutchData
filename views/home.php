<?php require __DIR__ . '/layouts/header.php'; ?>

<!-- Top Bar: Tabs & Actions -->
<div
    class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 md:mb-8 gap-4 border-b border-zinc-800 pb-6">

    <!-- Game Tabs (Zinc Theme) -->
    <div class="flex flex-col gap-3 w-full lg:w-auto">
        <!-- Game Tabs - Scrollable on mobile -->
        <div class="overflow-x-auto scrollbar-hide -mx-4 px-4 md:mx-0 md:px-0">
            <div class="flex bg-zinc-900 p-1 rounded-sm border border-zinc-800 w-max md:w-fit">
                <?php
                $tabs = [
                    'all' => 'ALL GAMES',
                    'valorant' => 'VALORANT',
                    'lol' => 'LEAGUE',
                    'cs2' => 'CS2'
                ];
                // $activeTab, $activeRegion, $activeStatus passed from Controller
                // Build query params helper
                $buildUrl = function ($game = null, $region = null, $status = null) use ($activeTab, $activeRegion, $activeStatus) {
                    $params = [];
                    $g = $game ?? $activeTab;
                    // If game is 'all' (default), we might use '.' as base, but for consistency let's stick to query strings or path
                    // Existing logic uses path for game type.
                    $baseUrl = ($g === 'all' || $g === null) ? '.' : $g;

                    $r = $region ?? $activeRegion;
                    if ($r && $r !== 'all')
                        $params['region'] = $r;

                    $s = $status ?? $activeStatus;
                    if ($s && $s !== 'all')
                        $params['status'] = $s;

                    $queryString = !empty($params) ? '?' . http_build_query($params) : '';
                    return $baseUrl . $queryString;
                };
                ?>
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= $buildUrl($key, null, null) ?>"
                        class="px-4 md:px-6 py-2 rounded-sm text-xs font-bold tracking-wider transition-all duration-200 uppercase whitespace-nowrap <?= ($activeTab ?? 'all') === $key ? 'bg-indigo-600 text-white shadow-md' : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Region & Status Filters - Scrollable -->
        <div class="flex flex-col sm:flex-row gap-3">
            <!-- Region Tabs -->
            <div class="overflow-x-auto scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
                <div class="flex gap-2 w-max sm:w-auto">
                    <?php
                    $regions = [
                        'all' => 'ALL REGIONS',
                        'Americas' => 'AMERICAS',
                        'EMEA' => 'EMEA',
                        'Pacific' => 'PACIFIC',
                        'International' => 'INTL',
                        'Other' => 'OTHER'
                    ];
                    ?>
                    <?php foreach ($regions as $key => $label): ?>
                        <?php
                        $isActive = ($activeRegion ?? 'all') === $key;
                        ?>
                        <a href="<?= $buildUrl(null, $key, null) ?>"
                            class="px-3 py-1.5 rounded-sm text-[10px] font-bold tracking-wider transition-all duration-200 uppercase border whitespace-nowrap <?= $isActive ? 'bg-zinc-800 text-white border-zinc-600' : 'text-zinc-500 border-transparent hover:text-zinc-300 hover:bg-zinc-800/50' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status Tabs -->
            <div
                class="overflow-x-auto scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0 sm:border-l sm:border-zinc-800 sm:pl-4">
                <div class="flex gap-2 w-max sm:w-auto">
                    <?php
                    $statuses = [
                        'all' => 'ANY STATUS',
                        'upcoming' => 'UPCOMING',
                        'completed' => 'COMPLETED'
                    ];
                    ?>
                    <?php foreach ($statuses as $key => $label): ?>
                        <?php
                        $isActive = ($activeStatus ?? 'all') === $key;
                        ?>
                        <a href="<?= $buildUrl(null, null, $key) ?>"
                            class="px-3 py-1.5 rounded-sm text-[10px] font-bold tracking-wider transition-all duration-200 uppercase border whitespace-nowrap <?= $isActive ? 'bg-emerald-900/30 text-emerald-400 border-emerald-900/50' : 'text-zinc-500 border-transparent hover:text-zinc-300 hover:bg-zinc-800/50' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2 md:gap-3 flex-shrink-0">
        <!-- Message/Error Display (Compact) -->
        <?php if (isset($message)): ?>
            <span
                class="text-xs font-mono text-emerald-500 bg-emerald-500/10 px-2 md:px-3 py-1.5 rounded border border-emerald-500/20 flex items-center">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-2"></span><?= htmlspecialchars($message) ?>
            </span>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <span
                class="text-xs font-mono text-rose-500 bg-rose-500/10 px-2 md:px-3 py-1.5 rounded border border-rose-500/20 flex items-center">
                <span class="w-1.5 h-1.5 bg-rose-500 rounded-full mr-2"></span><?= htmlspecialchars($error) ?>
            </span>
        <?php endif; ?>

        <a href="scrape"
            class="group relative inline-flex items-center justify-center px-4 md:px-6 py-2 overflow-hidden font-medium text-indigo-500 transition duration-300 ease-out border border-indigo-600/50 rounded-sm hover:border-indigo-500 bg-indigo-500/5">
            <span class="mr-2 text-xs uppercase tracking-widest font-bold">Sync</span>
            <svg class="w-3 h-3 group-hover:rotate-180 transition-transform duration-500" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </a>

        <a href="reset"
            class="group relative inline-flex items-center justify-center px-3 md:px-4 py-2 overflow-hidden font-medium text-zinc-500 transition duration-300 ease-out border border-zinc-700 rounded-sm hover:border-rose-500/50 hover:text-rose-500 bg-zinc-800/50"
            title="Factory Reset">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </a>
    </div>
</div>

<!-- Matches Grid - Ordered by Date & Importance -->
<div class="space-y-6">
    <?php if (empty($matches)): ?>
        <div class="py-20 text-center border border-dashed border-zinc-800 rounded bg-zinc-900/30">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-zinc-800 mb-4 text-zinc-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-zinc-300 font-bold uppercase tracking-wide text-sm">No Data Stream</h3>
            <p class="text-zinc-500 text-xs mt-2 font-mono">Execute SYNC to retrieve match data.</p>
        </div>
    <?php else: ?>
        <!-- All Matches Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($matches as $match): ?>
                <?php
                // Professional minimal accents
                $accentColor = match ($match['game_type']) {
                    'valorant' => 'bg-rose-500',
                    'lol' => 'bg-sky-500',
                    'cs2' => 'bg-amber-500',
                    default => 'bg-zinc-500'
                };

                // Determinar si el resultado debe mostrarse borroso
                // (completado y NO estamos en la pestaña de completados)
                $isCompleted = ($match['match_status'] ?? 'upcoming') !== 'upcoming';
                $shouldBlur = $isCompleted && ($activeStatus ?? 'all') !== 'completed';
                $matchId = $match['id'];
                ?>
                <a href="match?id=<?= $match['id'] ?>"
                    class="group relative block bg-zinc-900 border border-zinc-800 hover:border-zinc-700 hover:bg-zinc-800/80 transition-all duration-300 rounded-sm">
                    <!-- Game Indicator Strip -->
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 <?= $accentColor ?> rounded-l-sm opacity-50 group-hover:opacity-100 transition-opacity">
                    </div>

                    <div class="p-5 pl-7">
                        <!-- Header -->
                        <div class="flex justify-between items-start mb-6">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold tracking-[0.2em] text-zinc-500 uppercase">
                                    <?= $match['game_type'] ?>
                                </span>
                                <span class="text-[10px] text-zinc-600 mt-1">
                                    <?= htmlspecialchars($match['match_region'] ?? 'Other') ?>
                                </span>
                            </div>
                            <div class="flex flex-col items-end">
                                <!-- Date & Time -->
                                <span class="text-xs font-bold text-zinc-300">
                                    <?= date('M j', strtotime($match['match_time'])) ?>
                                </span>
                                <span class="text-[10px] font-mono text-zinc-500">
                                    <?= date('H:i', strtotime($match['match_time'])) ?> UTC
                                </span>
                            </div>
                        </div>

                        <!-- Teams -->
                        <div class="flex items-center justify-between gap-2 mb-6">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-base sm:text-lg text-white truncate group-hover:text-indigo-400 transition-colors"
                                    title="<?= htmlspecialchars($match['team1_name']) ?>">
                                    <?= htmlspecialchars($match['team1_name']) ?>
                                </h3>
                            </div>
                            <div class="text-center flex-shrink-0 px-2">
                                <?php if (($match['match_status'] ?? 'upcoming') === 'upcoming'): ?>
                                    <span class="text-zinc-700 text-xs tracking-widest font-black">VS</span>
                                <?php else: ?>
                                    <div class="flex flex-col items-center relative">
                                        <!-- Score con blur condicional -->
                                        <div id="score-<?= $matchId ?>" class="relative">
                                            <span
                                                class="text-lg sm:text-xl font-black text-white tracking-wider whitespace-nowrap transition-all duration-300 <?= $shouldBlur ? 'blur-sm select-none' : '' ?>"
                                                data-blurred="<?= $shouldBlur ? 'true' : 'false' ?>">
                                                <?= $match['team1_score'] ?? 0 ?> - <?= $match['team2_score'] ?? 0 ?>
                                            </span>
                                            <?php if ($shouldBlur): ?>
                                                <!-- Botón para revelar resultado -->
                                                <button type="button"
                                                    onclick="event.preventDefault(); event.stopPropagation(); toggleSpoiler(<?= $matchId ?>);"
                                                    class="absolute inset-0 flex items-center justify-center bg-zinc-800/80 rounded transition-opacity hover:bg-zinc-700/80"
                                                    id="reveal-btn-<?= $matchId ?>" title="Revelar resultado">
                                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-zinc-400" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (($match['match_status'] ?? '') === 'live'): ?>
                                            <span class="text-[10px] font-bold text-rose-500 uppercase animate-pulse">LIVE</span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold text-zinc-500 uppercase">FINAL</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0 text-right">
                                <h3 class="font-bold text-base sm:text-lg text-white truncate group-hover:text-indigo-400 transition-colors"
                                    title="<?= htmlspecialchars($match['team2_name']) ?>">
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
                                <span class="text-[10px] text-zinc-600 uppercase tracking-wider block mb-1">Win
                                    Prob</span>
                                <span class="text-lg font-mono font-bold text-white">
                                    <?= number_format($match['ai_prediction'], 0) ?><span
                                        class="text-indigo-500 text-sm">%</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>