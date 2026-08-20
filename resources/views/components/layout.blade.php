@props(['title' => 'TaskFlow'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('projects.index') }}" class="text-lg font-semibold text-slate-900">
                TaskFlow
            </a>

            <div class="flex items-center gap-6">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-600 hover:text-slate-900">
                        Tableau de bord
                    </a>
                    <a href="{{ route('notifications.index') }}" class="flex items-center gap-1 text-sm text-slate-600 hover:text-slate-900">
                        Notifications
                        @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
                        @if ($unread > 0)
                            <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs font-medium text-white">
                                {{ $unread }}
                            </span>
                        @endif
                    </a>
                    <a href="{{ route('profile.edit') }}" class="text-sm text-slate-600 hover:text-slate-900">
                        {{ auth()->user()->name }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-slate-600 hover:text-slate-900">
                            Déconnexion
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-slate-600 hover:text-slate-900">
                        Connexion
                    </a>
                    <a href="{{ route('register') }}" class="text-sm text-slate-600 hover:text-slate-900">
                        Inscription
                    </a>
                @endauth
            </div>
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
