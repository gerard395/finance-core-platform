<!DOCTYPE html>
<html lang="nl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Administratie kiezen – Finance Core</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
<main class="mx-auto max-w-4xl px-4 py-10">
    <header class="flex items-center justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-wider text-blue-700">Finance Core</p><h1 class="mt-2 text-2xl font-semibold">Kies een administratie</h1><p class="mt-2 text-slate-600">Ingelogd als {{ $domainUser->displayName()->toString() }}</p></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="min-h-11 rounded-lg border border-slate-300 px-4 py-2">Uitloggen</button></form></header>
    @error('administration_id')<p class="mt-6 rounded-lg bg-red-50 p-3 text-red-700" role="alert">{{ $message }}</p>@enderror
    <section class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($administrations as $administration)
            <form method="POST" action="{{ route('administrations.select.store') }}" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">@csrf
                <input type="hidden" name="administration_id" value="{{ $administration->id()->toString() }}">
                <h2 class="font-semibold">{{ $administration->name()->toString() }}</h2><p class="mt-1 text-sm text-slate-500">{{ $administration->code()->toString() }}</p>
                <button class="mt-5 min-h-11 w-full rounded-lg bg-blue-700 px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Selecteren</button>
            </form>
        @empty
            <p class="rounded-xl bg-white p-5 text-slate-600 ring-1 ring-slate-200">Geen toegankelijke administraties beschikbaar.</p>
        @endforelse
    </section>
</main></body></html>
