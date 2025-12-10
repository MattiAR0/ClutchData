<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MultiGame Stats - Proyecto Final</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-900 text-white font-sans antialiased">

    <div class="container mx-auto px-4 py-8">
        <header class="mb-10 text-center">
            <h1 class="text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-600">
                MultiGame Stats
            </h1>
            <p class="text-slate-400 mt-2">Plataforma de Estadísticas para Valorant, LoL y CS2</p>
        </header>

        <!-- Panel de Control y Estado -->
        <div class="mb-8 p-4 bg-slate-800 rounded-lg shadow-lg border border-slate-700">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h2 class="text-xl font-semibold text-blue-300">Panel de Control</h2>
                    <?php if (isset($message)): ?>
                        <p class="text-green-400 text-sm mt-1">✓ <?= htmlspecialchars($message) ?></p>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <p class="text-red-400 text-sm mt-1">⚠ <?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                </div>
                <a href="/index.php?scrape=1" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 rounded-md font-bold transition duration-200 shadow-md">
                    Ejecutar Scraping (Simulación)
                </a>
            </div>
        </div>

        <!-- Lista de Partidos -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($matches)): ?>
                <div class="col-span-full text-center py-10 bg-slate-800/50 rounded-xl border-dashed border-2 border-slate-700">
                    <p class="text-slate-500">No hay partidos registrados. ¡Ejecuta el scraper!</p>
                </div>
            <?php else: ?>
                <?php foreach ($matches as $match): ?>
                    <?php
                    $gameColor = match ($match['game_type']) {
                        'valorant' => 'text-red-400 border-red-900/50',
                        'lol' => 'text-blue-400 border-blue-900/50',
                        'cs2' => 'text-yellow-400 border-yellow-900/50',
                        default => 'text-gray-400'
                    };
                    $predictionColor = $match['ai_prediction'] > 50 ? 'text-green-400' : 'text-orange-400';
                    ?>
                    <div class="bg-slate-800 rounded-xl p-5 shadow-lg border border-slate-700 hover:border-slate-600 transition duration-300 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-2 opacity-10 font-black text-6xl uppercase transform rotate-12 pointer-events-none">
                            <?= $match['game_type'] ?>
                        </div>

                        <div class="flex justify-between items-center mb-4">
                            <span class="text-xs font-bold uppercase tracking-wider <?= $gameColor ?> border px-2 py-1 rounded bg-slate-900/50">
                                <?= $match['game_type'] ?>
                            </span>
                            <span class="text-xs text-slate-400">
                                <?= date('d M H:i', strtotime($match['match_time'])) ?>
                            </span>
                        </div>

                        <div class="flex justify-between items-center mb-6">
                            <div class="text-center w-1/2">
                                <h3 class="font-bold text-lg truncate"><?= htmlspecialchars($match['team1_name']) ?></h3>
                            </div>
                            <span class="text-slate-500 font-bold px-2">VS</span>
                            <div class="text-center w-1/2">
                                <h3 class="font-bold text-lg truncate"><?= htmlspecialchars($match['team2_name']) ?></h3>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-700 flex justify-between items-end">
                            <div class="text-xs text-slate-400">
                                <p class="truncate max-w-[120px]" title="<?= htmlspecialchars($match['tournament_name']) ?>">
                                    <?= htmlspecialchars($match['tournament_name']) ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="block text-xs text-slate-400 uppercase">AI Win Prob (T1)</span>
                                <span class="font-mono font-bold text-lg <?= $predictionColor ?>">
                                    <?= number_format($match['ai_prediction'], 1) ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>

</html>