<x-layouts.app :$domainUser :$administrationContext title="Dashboard">
    <header><h1 class="text-2xl font-semibold">Dashboard</h1><p class="mt-2 text-slate-600">Welkom, {{ $domainUser->displayName()->toString() }}. Financiële inzichten worden in een volgende story gekoppeld.</p></header>
    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Dashboardoverzicht">
        @foreach (['Omzet', 'Openstaande debiteuren', 'Openstaande crediteuren', 'BTW-positie'] as $label)
            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><h2 class="font-semibold">{{ $label }}</h2><p class="mt-4 text-sm text-slate-500">Nog geen data gekoppeld</p></article>
        @endforeach
    </section>
</x-layouts.app>
