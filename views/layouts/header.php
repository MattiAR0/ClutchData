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
    </style>
</head>

<body
    class="bg-zinc-950 text-zinc-200 antialiased min-h-screen flex flex-col selection:bg-indigo-500 selection:text-white">

    <nav class="border-b border-zinc-800 bg-zinc-900/50 backdrop-blur-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="w-2 h-8 bg-indigo-600 rounded-sm"></div>
                <h1 class="text-2xl font-bold tracking-widest text-white brand">
                    CLUTCH<span class="text-indigo-500">DATA</span>
                </h1>
            </div>
            <div class="text-xs font-mono text-zinc-500">
                ALPHA BUILD v0.1
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 flex-grow">