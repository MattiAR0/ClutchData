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
    document.addEventListener('DOMContentLoaded', function () {
        // Small delay to prioritize UI rendering
        setTimeout(function () {
            const indicator = document.getElementById('sync-indicator');
            const status = document.getElementById('sync-status');

            // Show syncing state
            indicator.classList.remove('bg-green-500');
            indicator.classList.add('bg-yellow-500', 'animate-pulse');
            status.textContent = 'Syncing...';

            fetch('./api/auto-update')
                .then(response => response.json())
                .then(data => {
                    indicator.classList.remove('bg-yellow-500', 'animate-pulse');

                    if (data.success) {
                        indicator.classList.add('bg-green-500');

                        if (data.updated) {
                            // Calculate total new matches
                            let totalNew = 0;
                            if (data.results) {
                                for (const game in data.results) {
                                    totalNew += data.results[game].new || 0;
                                }
                            }

                            if (totalNew > 0 || data.matches_with_maps_updated > 0) {
                                let msg = [];
                                if (totalNew > 0) msg.push(`+${totalNew} matches`);
                                if (data.matches_with_maps_updated > 0) msg.push(`+${data.matches_with_maps_updated} maps`);
                                status.textContent = msg.join(', ');
                                // Reload page to show new data after 2 seconds
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                status.textContent = 'Up to Date';
                            }
                        } else if (data.next_update_in) {
                            status.textContent = `Next sync: ${Math.ceil(data.next_update_in / 60)}m`;
                        } else {
                            status.textContent = 'Data Fresh';
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