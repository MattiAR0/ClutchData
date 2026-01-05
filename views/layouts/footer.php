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

    // Background Sync - fires after page loads, doesn't block rendering
    document.addEventListener('DOMContentLoaded', function() {
        // Small delay to prioritize UI rendering
        setTimeout(function() {
            const indicator = document.getElementById('sync-indicator');
            const status = document.getElementById('sync-status');

            // Show syncing state
            indicator.classList.remove('bg-green-500');
            indicator.classList.add('bg-yellow-500', 'animate-pulse');
            status.textContent = 'Syncing...';

            fetch('../api_sync.php')
                .then(response => response.json())
                .then(data => {
                    indicator.classList.remove('bg-yellow-500', 'animate-pulse');

                    if (data.success) {
                        indicator.classList.add('bg-green-500');
                        if (data.synced > 0) {
                            status.textContent = `+${data.synced} new matches`;
                            // Reload page to show new data after 2 seconds
                            setTimeout(() => location.reload(), 2000);
                        } else if (data.skipped) {
                            status.textContent = 'Data Fresh';
                        } else {
                            status.textContent = 'Up to Date';
                        }
                    } else {
                        indicator.classList.add('bg-red-500');
                        status.textContent = 'Sync Error';
                    }
                })
                .catch(err => {
                    indicator.classList.remove('bg-yellow-500', 'animate-pulse');
                    indicator.classList.add('bg-green-500');
                    status.textContent = 'Offline Mode';
                });
        }, 1000); // Wait 1 second after page load
    });
</script>

</body>

</html>