<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingelogd – Finance Core</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-10">
        <section class="w-full rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-700">Finance Core</p>
            <h1 class="mt-2 text-2xl font-semibold">Ingelogd</h1>
            <p class="mt-3 text-slate-600">Welkom, {{ $domainUser->displayName()->toString() }}.</p>
            <p class="mt-2 text-sm text-slate-500">Administratieselectie volgt in een volgende stap.</p>

            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button type="submit" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 font-semibold hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">
                    Uitloggen
                </button>
            </form>
        </section>
    </main>
</body>
</html>
