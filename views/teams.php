<?php require __DIR__ . '/layouts/header.php'; ?>

<!-- Top Bar: Tabs & Actions -->
<div
    class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 md:mb-8 gap-4 border-b border-zinc-800 pb-6">

    <!-- Navigation Tabs -->
    <div class="flex flex-col gap-3 w-full lg:w-auto">
        <div class="flex gap-2">
            <a href="."
                class="px-4 py-2 rounded-sm text-xs font-bold tracking-wider transition-all duration-200 uppercase text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800 border border-zinc-700">
                ← MATCHES
            </a>
        </div>

        <!-- Game Tabs - Scrollable -->
        <div class="overflow-x-auto scrollbar-hide -mx-4 px-4 md:mx-0 md:px-0">
            <div class="flex bg-zinc-900 p-1 rounded-sm border border-zinc-800 w-max md:w-fit">
                <?php
                $tabs = [
                    'all' => 'ALL GAMES',
                    'valorant' => 'VALORANT',
                    'lol' => 'LEAGUE',
                    'cs2' => 'CS2'
                ];
                $buildUrl = function ($game = null, $region = null) use ($activeTab, $activeRegion) {
                    $params = [];
                    $g = $game ?? $activeTab;
                    if ($g !== 'all')
                        $params['game'] = $g;

                    $r = $region ?? $activeRegion;
                    if ($r && $r !== 'all')
                        $params['region'] = $r;

                    $queryString = !empty($params) ? '?' . http_build_query($params) : '';
                    return 'teams' . $queryString;
                };
                ?>
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= $buildUrl($key, null) ?>"
                        class="px-4 md:px-6 py-2 rounded-sm text-xs font-bold tracking-wider transition-all duration-200 uppercase whitespace-nowrap <?= ($activeTab ?? 'all') === $key ? 'bg-indigo-600 text-white shadow-md' : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Region Tabs - Scrollable -->
        <div class="overflow-x-auto scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
            <div class="flex gap-2 w-max sm:w-auto">
                <?php
                $regions = [
                    'all' => 'ALL REGIONS',
                    'Americas' => 'AMERICAS',
                    'EMEA' => 'EMEA',
                    'Pacific' => 'PACIFIC',
                    'Other' => 'OTHER'
                ];
                ?>
                <?php foreach ($regions as $key => $label): ?>
                    <?php $isActive = ($activeRegion ?? 'all') === $key; ?>
                    <a href="<?= $buildUrl(null, $key) ?>"
                        class="px-3 py-1.5 rounded-sm text-[10px] font-bold tracking-wider transition-all duration-200 uppercase border whitespace-nowrap <?= $isActive ? 'bg-zinc-800 text-white border-zinc-600' : 'text-zinc-500 border-transparent hover:text-zinc-300 hover:bg-zinc-800/50' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="text-right flex-shrink-0">
        <span class="text-xs font-mono text-zinc-500">
            <?= count($teams) ?> TEAMS FOUND
        </span>
    </div>
</div>

<!-- Teams Grid -->
<div class="space-y-8">
    <?php if (empty($teams)): ?>
        <div class="py-20 text-center border border-dashed border-zinc-800 rounded bg-zinc-900/30">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-zinc-800 mb-4 text-zinc-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <h3 class="text-zinc-300 font-bold uppercase tracking-wide text-sm">No Teams Found</h3>
            <p class="text-zinc-500 text-xs mt-2 font-mono">Sync matches first to populate teams list.</p>
            <a href="scrape"
                class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-sm hover:bg-indigo-500 transition-colors">
                SYNC MATCHES
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($teams as $team): ?>
                <?php
                $accentColor = match ($team['game_type']) {
                    'valorant' => 'bg-rose-500',
                    'lol' => 'bg-sky-500',
                    'cs2' => 'bg-amber-500',
                    default => 'bg-zinc-500'
                };
                $gameLabel = match ($team['game_type']) {
                    'valorant' => 'VAL',
                    'lol' => 'LOL',
                    'cs2' => 'CS2',
                    default => '???'
                };
                ?>
                <a href="team?name=<?= urlencode($team['name']) ?>&game=<?= $team['game_type'] ?>"
                    class="group relative block bg-zinc-900 border border-zinc-800 hover:border-zinc-700 hover:bg-zinc-800/80 transition-all duration-300 rounded-sm overflow-hidden">

                    <!-- Game Indicator Strip -->
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 <?= $accentColor ?> opacity-50 group-hover:opacity-100 transition-opacity">
                    </div>

                    <div class="p-6 pl-7">
                        <!-- Logo / Avatar -->
                        <div class="flex items-center gap-4 mb-4">
                            <?php if (!empty($team['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($team['logo_url']) ?>" alt="<?= htmlspecialchars($team['name']) ?>"
                                    class="w-12 h-12 object-contain rounded bg-zinc-800 p-1">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded bg-zinc-800 flex items-center justify-center text-zinc-600">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-lg text-white truncate group-hover:text-indigo-400 transition-colors"
                                    title="<?= htmlspecialchars($team['name']) ?>">
                                    <?= htmlspecialchars($team['name']) ?>
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-bold tracking-[0.2em] text-zinc-500 uppercase">
                                        <?= $gameLabel ?>
                                    </span>
                                    <?php if (!empty($team['region']) && $team['region'] !== 'Unknown'): ?>
                                        <span class="text-[10px] text-zinc-600">•</span>
                                        <span class="text-[10px] text-zinc-500">
                                            <?= htmlspecialchars($team['region']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="pt-4 border-t border-zinc-800">
                            <span class="text-[10px] text-zinc-600 uppercase tracking-wider">
                                Click to view roster & results →
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>