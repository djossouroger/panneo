<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pannéo — Administration</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-6 text-center">
            <div class="relative mx-auto flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl bg-blue-600 text-white">
                <span class="absolute left-2 top-4 h-1 w-5 rounded-full bg-white"></span>
                <span class="absolute bottom-4 right-2 h-1 w-5 rounded-full bg-white"></span>
                <span class="absolute h-2 w-2 rounded-full bg-orange-400"></span>
                <svg class="relative h-6 w-6 -rotate-45" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.66 5.66L3 18l3 3 6.04-6.04A4 4 0 0 0 17.7 9.3l-3 3-3-3 3-3Z"/></svg>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-slate-900">Pannéo</h1>
            <p class="mt-1 text-sm text-slate-500">Administration</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:border-blue-500 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Mot de passe</label>
                <input type="password" name="password" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-900 focus:border-blue-500 focus:outline-none" />
            </div>
            <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-700">Se connecter</button>
        </form>
    </div>
</body>
</html>
