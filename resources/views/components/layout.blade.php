@props(['title' => 'TaskFlow'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('projects.index') }}" class="text-lg font-semibold text-slate-900">
                TaskFlow
            </a>

            <a href="{{ route('dashboard') }}" class="text-sm text-slate-600 hover:text-slate-900">
                Tableau de bord
            </a>
        </nav>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('success'))
            <div class="mb-6 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
