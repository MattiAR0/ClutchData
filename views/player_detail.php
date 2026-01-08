<?php require __DIR__ . '/layouts/header.php'; ?>

<?php
$accentColor = match ($player['game_type']) {
    'valorant' => 'rose',
    'lol' => 'sky',
    'cs2' => 'amber',
    default => 'zinc'
};
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-2 mb-6 text-sm overflow-x-auto scrollbar-hide">
    <a href="." class="text-zinc-500 hover:text-white transition-colors">Home</a>
    <span class="text-zinc-700">/</span>
    <a href="teams?game=<?= $player['game_type'] ?>" class="text-zinc-500 hover:text-white transition-colors">Teams</a>
    <span class="text-zinc-700">/</span>
    <span class="text-white font-medium"><?= htmlspecialchars($player['nickname']) ?></span>
</div>

<!-- Player Header -->
<div class="bg-zinc-900 border border-zinc-800 rounded-sm p-8 mb-8">
    <div class="flex flex-col md:flex-row items-start gap-8">
        <!-- Photo -->
        <div class="flex-shrink-0">
            <?php if (!empty($player['photo_url'])): ?>
                <img src="image-proxy?url=<?= urlencode($player['photo_url']) ?>"
                    alt="<?= htmlspecialchars($player['nickname']) ?>"
                    class="w-40 h-40 object-cover rounded-lg bg-zinc-800">
            <?php else: ?>
                <div class="w-40 h-40 rounded-lg bg-zinc-800 flex items-center justify-center text-zinc-600">
                    <svg class="w-20 h-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="flex-1">
            <div class="flex items-center gap-4 mb-2">
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-white uppercase tracking-tight">
                    <?= htmlspecialchars($player['nickname']) ?>
                </h1>
                <span
                    class="px-3 py-1 bg-<?= $accentColor ?>-500/20 text-<?= $accentColor ?>-400 text-xs font-bold uppercase rounded">
                    <?= strtoupper($player['game_type']) ?>
                </span>
            </div>

            <?php if (!empty($player['real_name'])): ?>
                <p class="text-zinc-400 text-lg mb-4"><?= htmlspecialchars($player['real_name']) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-6 text-sm mb-6">
                <?php if (!empty($player['team_name'])): ?>
                    <div>
                        <span class="text-zinc-500 text-xs uppercase tracking-wider block mb-1">Current Team</span>
                        <a href="team?name=<?= urlencode($player['team_name']) ?>&game=<?= $player['game_type'] ?>"
                            class="text-indigo-400 hover:text-indigo-300 font-medium transition-colors">
                            <?= htmlspecialchars($player['team_name']) ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($player['role'])): ?>
                    <div>
                        <span class="text-zinc-500 text-xs uppercase tracking-wider block mb-1">Role</span>
                        <span class="text-white font-medium"><?= htmlspecialchars($player['role']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($player['country'])): ?>
                    <div>
                        <span class="text-zinc-500 text-xs uppercase tracking-wider block mb-1">Country</span>
                        <span class="text-white font-medium"><?= htmlspecialchars($player['country']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($player['birthdate'])): ?>
                    <div>
                        <span class="text-zinc-500 text-xs uppercase tracking-wider block mb-1">Birthdate</span>
                        <span class="text-white font-medium"><?= date('M j, Y', strtotime($player['birthdate'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($player['description'])): ?>
                <p class="text-zinc-400 text-sm leading-relaxed max-w-3xl">
                    <?= htmlspecialchars(substr($player['description'], 0, 400)) ?>
                    <?= strlen($player['description']) > 400 ? '...' : '' ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($player['liquipedia_url'])): ?>
                <a href="<?= htmlspecialchars($player['liquipedia_url']) ?>" target="_blank"
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Match Stats -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-sm">
        <div class="px-6 py-4 border-b border-zinc-800">
            <h2 class="text-lg font-bold text-white uppercase tracking-wide flex items-center gap-2">
                <span class="w-1 h-6 bg-indigo-500 rounded"></span>
                Match Statistics
            </h2>
        </div>

        <div class="divide-y divide-zinc-800">
            <?php if (empty($matchStats)): ?>
                <div class="p-6 text-center text-zinc-500 text-sm">
                    No match statistics available yet.
                </div>
            <?php else: ?>
                <?php foreach (array_slice($matchStats, 0, 10) as $stat): ?>
                    <div class="flex items-center justify-between p-4 hover:bg-zinc-800/30">
                        <div class="flex items-center gap-4">
                            <div class="text-center w-14">
                                <span class="text-xs font-bold text-zinc-300 block">
                                    <?= date('M j', strtotime($stat['match_time'])) ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-zinc-400 text-sm">
                                    <?= htmlspecialchars($stat['team1_name']) ?> vs <?= htmlspecialchars($stat['team2_name']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <!-- KDA -->
                            <div class="text-right">
                                <span class="font-mono font-bold text-white">
                                    <?= $stat['kills'] ?>/<?= $stat['deaths'] ?>/<?= $stat['assists'] ?>
                                </span>
                                <span class="text-xs text-zinc-500 block">KDA</span>
                            </div>
                            <?php if (!empty($stat['agent'])): ?>
                                <span class="text-xs text-zinc-500 w-16 truncate" title="<?= htmlspecialchars($stat['agent']) ?>">
                                    <?= htmlspecialchars($stat['agent']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Team History -->
    <div class="bg-zinc-900 border border-zinc-800 rounded-sm">
        <div class="px-6 py-4 border-b border-zinc-800">
            <h2 class="text-lg font-bold text-white uppercase tracking-wide flex items-center gap-2">
                <span class="w-1 h-6 bg-<?= $accentColor ?>-500 rounded"></span>
                Team History
            </h2>
        </div>

        <div class="divide-y divide-zinc-800">
            <?php if (empty($teamHistory)): ?>
                <div class="p-6 text-center text-zinc-500 text-sm">
                    No team history available.
                </div>
            <?php else: ?>
                <?php foreach ($teamHistory as $history): ?>
                    <a href="team?name=<?= urlencode($history['team']) ?>&game=<?= $player['game_type'] ?>"
                        class="flex items-center gap-4 p-4 hover:bg-zinc-800/50 transition-colors group">
                        <div
                            class="w-10 h-10 rounded bg-zinc-800 flex items-center justify-center text-zinc-500 group-hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                            </svg>
                        </div>
                        <span class="font-medium text-white group-hover:text-indigo-400 transition-colors">
                            <?= htmlspecialchars($history['team']) ?>
                        </span>
                        <svg class="w-4 h-4 text-zinc-600 group-hover:text-white transition-colors ml-auto" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Achievements -->
    <?php if (!empty($achievements)): ?>
        <div class="lg:col-span-2 bg-zinc-900 border border-zinc-800 rounded-sm">
            <div class="px-6 py-4 border-b border-zinc-800">
                <h2 class="text-lg font-bold text-white uppercase tracking-wide flex items-center gap-2">
                    <span class="w-1 h-6 bg-amber-500 rounded"></span>
                    Achievements
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                <?php foreach ($achievements as $achievement): ?>
                    <div class="bg-zinc-800/50 rounded p-4">
                        <span
                            class="text-xs text-zinc-500 block mb-1"><?= htmlspecialchars($achievement['year'] ?? '') ?></span>
                        <span class="text-white font-medium"><?= htmlspecialchars($achievement['achievement'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>