<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>@yield('title', 'Pannéo Admin')</title>
        @if (file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900 antialiased">
        <div class="min-h-screen flex">
            <aside class="relative w-72 border-r border-slate-200 bg-white">
                <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-200">
                    <div class="relative flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-blue-600 text-white">
                        <span class="absolute left-1.5 top-3 h-1 w-4 rounded-full bg-white"></span>
                        <span class="absolute bottom-3 right-1.5 h-1 w-4 rounded-full bg-white"></span>
                        <span class="absolute h-2 w-2 rounded-full bg-orange-400"></span>
                        <svg class="relative h-5 w-5 -rotate-45" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.66 5.66L3 18l3 3 6.04-6.04A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3Z"/></svg>
                    </div>
                    <div>
                        <p class="text-lg font-bold text-slate-900">Pannéo</p>
                        <p class="text-xs text-slate-500">Administration</p>
                    </div>
                </div>
                <nav class="p-4 space-y-2 pb-28">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                        Tableau de bord
                    </a>
                    <a href="{{ route('admin.repair-requests.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.repair-requests.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M8 3h8l1 2h3v16H4V5h3l1-2Z"/><path d="M8 11h8M8 15h5"/></svg>
                        Demandes
                    </a>
                    <a href="{{ route('admin.users') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.users') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="7" r="3.5"/><path d="M20 19v-1a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Utilisateurs
                    </a>
                    <a href="{{ route('admin.artisans') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.artisans.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M14.7 6.3 17.7 9.3"/><path d="M9 18 17.7 9.3l-2-2L7 16l2 2Z"/><path d="m3 21 4.5-1.5L4.5 16.5 3 21Z"/></svg>
                        Artisans
                    </a>
                    <a href="{{ route('admin.verifications') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.verifications.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M9 12 11 14 15 10"/><path d="M20 12a8 8 0 1 1-2.34-5.66"/></svg>
                        Vérifications
                    </a>
                    <a href="{{ route('admin.disputes') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.disputes.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                        Litiges
                    </a>
                    <a href="{{ route('admin.reviews') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.reviews') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 17.27L18.36 21l-6.36-12L6 11l6.36 8.27z"/></svg>
                        Avis
                    </a>
                    <a href="{{ route('admin.categories') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.categories') ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Z"/><path d="M8 9h8M8 12h8M8 15h5"/></svg>
                        Catégories
                    </a>
                </nav>
                <div class="absolute bottom-0 left-0 right-0 border-t border-slate-200 bg-white p-4 hidden md:block">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="font-medium text-sm text-slate-900">{{ auth()->user()->name ?? 'Admin' }}</p>
                            <p class="text-xs text-slate-500">Administrateur</p>
                        </div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-slate-600 hover:text-slate-900">Déconnexion</button>
                        </form>
                    </div>
                </div>
            </aside>
            <main class="flex-1">
                <header class="border-b border-slate-200 bg-white px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-900">@yield('page_title')</h1>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <span class="rounded-full bg-green-100 text-green-700 px-2.5 py-1 text-xs font-medium">En ligne</span>
                            <span>{{ auth()->user()->name ?? 'Admin' }}</span>
                        </div>
                    </div>
                </header>
                <div class="p-6">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>