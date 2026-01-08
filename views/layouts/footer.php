</div>

<footer class="bg-zinc-900 border-t border-zinc-800 text-zinc-500 mt-12 py-8">
    <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
        <div class="mb-4 md:mb-0">
            <p class="text-sm">&copy; <?= date('Y') ?> ClutchData Logic.</p>
            <p class="text-xs text-zinc-600 mt-1">Professional Esports Analytics Platform.</p>
        </div>
        <div class="flex space-x-4 items-center">
            <span id="sync-indicator" class="w-2 h-2 rounded-full bg-green-500"></span>
            <span id="sync-status" class="text-xs font-mono uppercase tracking-wider">System Operational</span>
        </div>
    </div>
</footer>

<!-- Spoiler Toggle Script -->
<script>
    function toggleSpoiler(matchId) {
        const scoreContainer = document.getElementById('score-' + matchId);
        const revealBtn = document.getElementById('reveal-btn-' + matchId);
        const scoreSpan = scoreContainer.querySelector('span');

        if (scoreSpan.classList.contains('blur-sm')) {
            // Revelar resultado
            scoreSpan.classList.remove('blur-sm', 'select-none');
            if (revealBtn) {
                revealBtn.style.display = 'none';
            }
        } else {
            // Volver a ocultar
            scoreSpan.classList.add('blur-sm', 'select-none');
            if (revealBtn) {
                revealBtn.style.display = 'flex';
            }
        }
    }
</script>

</body>

</html>