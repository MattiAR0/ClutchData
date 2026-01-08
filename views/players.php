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
            <a href="teams"
                class="px-4 py-2 rounded-sm text-xs font-bold tracking-wider transition-all duration-200 uppercase text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800 border border-zinc-700">
                TEAMS
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
                $buildUrl = function ($game = null) use ($activeTab) {
                    $params = [];
                    $g = $game ?? $activeTab;
                    if ($g !== 'all')
                        $params['game'] = $g;

                    $queryString = !empty($params) ? '?' . http_build_query($params) : '';
                    return 'players' . $queryString;
                };
                ?>
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= $buildUrl($key) ?>"
                        class="px-4 md:px-6 py-2 rounded-sm text-xs font-bold tracking-wider transition-all duration-200 uppercase whitespace-nowrap <?= ($activeTab ?? 'all') === $key ? 'bg-indigo-600 text-white shadow-md' : 'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Info & Actions -->
    <div class="flex flex-col items-end gap-3 flex-shrink-0">
        <span class="text-xs font-mono text-zinc-500">
            <?= count($players) ?> PLAYERS FOUND
        </span>

        <!-- Sync Actions -->
        <div class="flex gap-2 flex-wrap justify-end">
            <a href="players/sync<?= isset($activeTab) && $activeTab !== 'all' ? '?game=' . $activeTab : '' ?>"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-600/20 border border-emerald-600/40 text-emerald-400 text-xs font-bold rounded-sm hover:bg-emerald-600/30 hover:border-emerald-500 transition-all duration-200"
                title="Sync players from match statistics">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                SYNC FROM STATS
            </a>
            <a href="players/sync-from-teams<?= isset($activeTab) && $activeTab !== 'all' ? '?game=' . $activeTab : '' ?>"
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600/20 border border-indigo-600/40 text-indigo-400 text-xs font-bold rounded-sm hover:bg-indigo-600/30 hover:border-indigo-500 transition-all duration-200"
                title="Sync player rosters from teams">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                SYNC FROM TEAMS
            </a>
            <?php if (isset($activeTab) && $activeTab !== 'all'): ?>
                <a href="players/discover?game=<?= $activeTab ?>"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-sm transition-all duration-200"
                    title="Force refresh player list from Liquipedia">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    REFRESH LIST
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Status Messages -->
<?php if (!empty($message)): ?>
    <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-sm">
        <p class="text-emerald-400 text-sm"><?= $message ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="mb-6 p-4 bg-rose-500/10 border border-rose-500/30 rounded-sm">
        <p class="text-rose-400 text-sm"><?= htmlspecialchars($error) ?></p>
    </div>
<?php endif; ?>

<!-- Players Grid -->
<div class="space-y-8">
    <?php if (empty($players)): ?>
        <div class="py-20 text-center border border-dashed border-zinc-800 rounded bg-zinc-900/30">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-zinc-800 mb-4 text-zinc-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h3 class="text-zinc-300 font-bold uppercase tracking-wide text-sm">No Players Found</h3>
            <p class="text-zinc-500 text-xs mt-2 font-mono">Sync teams first to populate players list.</p>
            <a href="teams"
                class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-sm hover:bg-indigo-500 transition-colors">
                GO TO TEAMS
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($players as $player): ?>
                <?php
                $accentColor = match ($player['game_type']) {
                    'valorant' => 'bg-rose-500',
                    'lol' => 'bg-sky-500',
                    'cs2' => 'bg-amber-500',
                    default => 'bg-zinc-500'
                };
                $gameLabel = match ($player['game_type']) {
                    'valorant' => 'VAL',
                    'lol' => 'LOL',
                    'cs2' => 'CS2',
                    default => '???'
                };
                ?>
                <a href="player?name=<?= urlencode($player['nickname']) ?>&game=<?= $player['game_type'] ?>"
                    class="group relative block bg-zinc-900 border border-zinc-800 hover:border-zinc-700 hover:bg-zinc-800/80 transition-all duration-300 rounded-sm overflow-hidden">

                    <!-- Game Indicator Strip -->
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 <?= $accentColor ?> opacity-50 group-hover:opacity-100 transition-opacity">
                    </div>

                    <div class="p-6 pl-7">
                        <!-- Photo / Avatar -->
                        <div class="flex items-center gap-4 mb-4">
                            <?php if (!empty($player['photo_url'])): ?>
                                <img src="<?= htmlspecialchars($player['photo_url']) ?>"
                                    alt="<?= htmlspecialchars($player['nickname']) ?>"
                                    class="w-12 h-12 object-cover rounded-full bg-zinc-800">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-zinc-800 flex items-center justify-center text-zinc-600">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-lg text-white truncate group-hover:text-indigo-400 transition-colors"
                                    title="<?= htmlspecialchars($player['nickname']) ?>">
                                    <?= htmlspecialchars($player['nickname']) ?>
                                </h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-bold tracking-[0.2em] text-zinc-500 uppercase">
                                        <?= $gameLabel ?>
                                    </span>
                                    <?php if (!empty($player['role'])): ?>
                                        <span class="text-[10px] text-zinc-600">•</span>
                                        <span class="text-[10px] text-zinc-500">
                                            <?= htmlspecialchars($player['role']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Team & Country -->
                        <div class="flex items-center gap-3 text-xs text-zinc-500 mb-4">
                            <?php if (!empty($player['team_name'])): ?>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                                    </svg>
                                    <?= htmlspecialchars($player['team_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($player['country'])): ?>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?= htmlspecialchars($player['country']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Footer -->
                        <div class="pt-4 border-t border-zinc-800">
                            <span class="text-[10px] text-zinc-600 uppercase tracking-wider">
                                Click to view profile & stats →
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>