<?php require __DIR__ . '/layouts/header.php'; ?>

<?php
$accentColor = match ($team['game_type']) {
    'valorant' => 'rose',
    'lol' => 'sky',
    'cs2' => 'amber',
    default => 'zinc'
};
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 mb-6 text-sm">
    <a href="." class="text-zinc-500 hover:text-white transition-colors">Home</a>
    <span class="text-zinc-700">/</span>
    <a href="teams?game=<?= $team['game_type'] ?>" class="text-zinc-500 hover:text-white transition-colors">Teams</a>
    <span class="text-zinc-700">/</span>
    <span class="text-white font-medium"><?= htmlspecialchars($team['name']) ?></span>
</div>

<!-- Team Header -->
<div class="bg-zinc-900 border border-zinc-800 rounded-sm p-8 mb-8">
    <div class="flex flex-col md:flex-row items-start gap-8">
        <!-- Logo -->
        <div class="flex-shrink-0">
            <?php if (!empty($team['logo_url'])): ?>
                <img src="<?= htmlspecialchars($team['logo_url']) ?>" alt="<?= htmlspecialchars($team['name']) ?>"
                    class="w-32 h-32 object-contain rounded-lg bg-zinc-800 p-4">
            <?php else: ?>
                <div class="w-32 h-32 rounded-lg bg-zinc-800 flex items-center justify-center text-zinc-600">
                    <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="flex-1">
            <div class="flex items-center gap-4 mb-4">
                <h1 class="text-4xl font-black text-white uppercase tracking-tight">
                    <?= htmlspecialchars($team['name']) ?>
                </h1>
                <span
                    class="px-3 py-1 bg-<?= $accentColor ?>-500/20 text-<?= $accentColor ?>-400 text-xs font-bold uppercase rounded">
                    <?= strtoupper($team['game_type']) ?>
                </span>
            </div>

            <div class="flex flex-wrap gap-6 text-sm mb-6">
                <?php if (!empty($team['region'])): ?>
                    <div>
                        <span class="text-zinc-500 text-xs uppercase tracking-wider block mb-1">Region</span>
                        <span class="text-white font-medium"><?= htmlspecialchars($team['region']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($team['country'])): ?>
                    <div>
                        <span class="text-zinc-500 text-xs uppercase tracking-wider block mb-1">Country</span>
                        <span class="text-white font-medium"><?= htmlspecialchars($team['country']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($team['description'])): ?>
                <p class="text-zinc-400 text-sm leading-relaxed max-w-3xl">
                    <?= htmlspecialchars(substr($team['description'], 0, 300)) ?>    <?= strlen($team['description']) > 300 ? '...' : '' ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($team['liquipedia_url'])): ?>
                <a href="<?= htmlspecialchars($team['liquipedia_url']) ?>" target="_blank"
                    class="inline-flex items-center gap-2 mt-4 text-indigo-400 hover:text-indigo-300 text-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    View on Liquipedia
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Roster Section -->
    <div class="lg:col-span-1">
        <div class="bg-zinc-900 border border-zinc-800 rounded-sm">
            <div class="px-6 py-4 border-b border-zinc-800">
                <h2 class="text-lg font-bold text-white uppercase tracking-wide flex items-center gap-2">
                    <span class="w-1 h-6 bg-<?= $accentColor ?>-500 rounded"></span>
                    Active Roster
                </h2>
            </div>

            <div class="divide-y divide-zinc-800">
                <?php if (empty($roster)): ?>
                    <div class="p-6 text-center text-zinc-500 text-sm">
                        No roster information available.
                    </div>
                <?php else: ?>
                    <?php foreach ($roster as $player): ?>
                        <a href="player?name=<?= urlencode($player['nickname']) ?>&game=<?= $team['game_type'] ?>"
                            class="flex items-center gap-4 p-4 hover:bg-zinc-800/50 transition-colors group">
                            <div
                                class="w-10 h-10 rounded-full bg-zinc-800 flex items-center justify-center text-zinc-500 group-hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-white group-hover:text-indigo-400 transition-colors truncate">
                                    <?= htmlspecialchars($player['nickname']) ?>
                                </h4>
                                <?php if (!empty($player['role'])): ?>
                                    <span class="text-xs text-zinc-500"><?= htmlspecialchars($player['role']) ?></span>
                                <?php endif; ?>
                            </div>
                            <svg class="w-4 h-4 text-zinc-600 group-hover:text-white transition-colors" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Matches Section -->
    <div class="lg:col-span-2">
        <div class="bg-zinc-900 border border-zinc-800 rounded-sm">
            <div class="px-6 py-4 border-b border-zinc-800">
                <h2 class="text-lg font-bold text-white uppercase tracking-wide flex items-center gap-2">
                    <span class="w-1 h-6 bg-indigo-500 rounded"></span>
                    Recent Matches
                </h2>
            </div>

            <div class="divide-y divide-zinc-800">
                <?php if (empty($matches)): ?>
                    <div class="p-6 text-center text-zinc-500 text-sm">
                        No matches found for this team.
                    </div>
                <?php else: ?>
                    <?php foreach ($matches as $match): ?>
                        <?php
                        $isTeam1 = $match['team1_name'] === $team['name'];
                        $teamScore = $isTeam1 ? ($match['team1_score'] ?? '-') : ($match['team2_score'] ?? '-');
                        $oppScore = $isTeam1 ? ($match['team2_score'] ?? '-') : ($match['team1_score'] ?? '-');
                        $opponent = $isTeam1 ? $match['team2_name'] : $match['team1_name'];
                        $isWin = is_numeric($teamScore) && is_numeric($oppScore) && $teamScore > $oppScore;
                        $isLoss = is_numeric($teamScore) && is_numeric($oppScore) && $teamScore < $oppScore;
                        ?>
                        <a href="match?id=<?= $match['id'] ?>"
                            class="flex items-center justify-between p-4 hover:bg-zinc-800/50 transition-colors group">
                            <div class="flex items-center gap-4">
                                <!-- Date -->
                                <div class="text-center w-14">
                                    <span class="text-xs font-bold text-zinc-300 block">
                                        <?= date('M j', strtotime($match['match_time'])) ?>
                                    </span>
                                    <span class="text-[10px] font-mono text-zinc-500">
                                        <?= date('H:i', strtotime($match['match_time'])) ?>
                                    </span>
                                </div>

                                <!-- Result Badge -->
                                <?php if ($match['match_status'] === 'completed'): ?>
                                    <span
                                        class="w-8 h-8 flex items-center justify-center rounded text-xs font-bold <?= $isWin ? 'bg-emerald-500/20 text-emerald-400' : ($isLoss ? 'bg-rose-500/20 text-rose-400' : 'bg-zinc-700 text-zinc-400') ?>">
                                        <?= $isWin ? 'W' : ($isLoss ? 'L' : 'D') ?>
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="w-8 h-8 flex items-center justify-center rounded text-xs font-bold bg-zinc-700 text-zinc-400">
                                        --
                                    </span>
                                <?php endif; ?>

                                <!-- Opponent -->
                                <div>
                                    <span class="text-zinc-400 text-sm">vs</span>
                                    <span class="font-bold text-white ml-2 group-hover:text-indigo-400 transition-colors">
                                        <?= htmlspecialchars($opponent) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <!-- Score -->
                                <?php if ($match['match_status'] === 'completed'): ?>
                                    <span class="font-mono font-bold text-lg text-white">
                                        <?= $teamScore ?> - <?= $oppScore ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-zinc-500 uppercase"><?= $match['match_status'] ?></span>
                                <?php endif; ?>

                                <!-- Tournament -->
                                <span class="text-xs text-zinc-500 max-w-[150px] truncate hidden sm:block"
                                    title="<?= htmlspecialchars($match['tournament_name']) ?>">
                                    <?= htmlspecialchars($match['tournament_name']) ?>
                                </span>

                                <svg class="w-4 h-4 text-zinc-600 group-hover:text-white transition-colors" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>