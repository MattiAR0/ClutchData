<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MultiGame Stats - Professional Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Professional Gaming Font */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Rajdhani:wght@500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        h1,
        h2,
        h3,
        .brand {
            font-family: 'Rajdhani', sans-serif;
            text-transform: uppercase;
        }

        /* Hide scrollbar but keep scroll functionality */
        .scrollbar-hide {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;  /* Chrome, Safari and Opera */
        }
    </style>
</head>

<body
    class="bg-zinc-950 text-zinc-200 antialiased min-h-screen flex flex-col selection:bg-indigo-500 selection:text-white">

    <nav class="border-b border-zinc-800 bg-zinc-900/50 backdrop-blur-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-6">
                <a href="." class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                    <div class="w-2 h-8 bg-indigo-600 rounded-sm"></div>
                    <h1 class="text-xl md:text-2xl font-bold tracking-widest text-white brand">
                        CLUTCH<span class="text-indigo-500">DATA</span>
                    </h1>
                </a>

                <!-- Desktop Navigation Links -->
                <div class="hidden md:flex items-center space-x-1 ml-8">
                    <a href="."
                        class="px-4 py-2 text-sm font-medium text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors">
                        Matches
                    </a>
                    <a href="teams"
                        class="px-4 py-2 text-sm font-medium text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors">
                        Teams
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-xs font-mono text-zinc-500 hidden sm:block">
                    ALPHA BUILD v0.2
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden p-2 text-zinc-400 hover:text-white transition-colors"
                    aria-label="Toggle menu">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path id="menu-icon-open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="md:hidden hidden border-t border-zinc-800 bg-zinc-900/95 backdrop-blur-sm">
            <div class="container mx-auto px-4 py-4 flex flex-col space-y-2">
                <a href="."
                    class="px-4 py-3 text-sm font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 rounded transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Matches
                </a>
                <a href="teams"
                    class="px-4 py-3 text-sm font-medium text-zinc-300 hover:text-white hover:bg-zinc-800 rounded transition-colors flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                    </svg>
                    Teams
                </a>
            </div>
        </div>
    </nav>

    <script>
        document.getElementById('mobile-menu-btn').addEventListener('click', function () {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>

    <div class="container mx-auto px-4 py-6 md:py-8 flex-grow">