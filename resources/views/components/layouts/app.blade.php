<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} – Finance Core</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
<a href="#main-content" class="sr-only z-50 rounded bg-white p-3 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Naar hoofdinhoud</a>
<div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
    <div data-menu-backdrop class="fixed inset-0 z-30 hidden bg-slate-950/60 lg:hidden"></div>
    <aside data-mobile-menu id="primary-navigation" class="fixed inset-y-0 left-0 z-40 hidden w-72 overflow-y-auto bg-slate-950 px-4 py-5 text-slate-200 lg:sticky lg:top-0 lg:block lg:h-screen lg:w-auto" aria-label="Hoofdnavigatie">
        <div class="flex items-center justify-between px-2"><span class="text-lg font-semibold text-white">Finance Core</span><button data-menu-close class="min-h-11 min-w-11 rounded-lg text-2xl focus:ring-2 focus:ring-blue-400 lg:hidden" aria-label="Menu sluiten">×</button></div>
        <nav class="mt-8 space-y-6">
            <x-navigation.section title="Overzicht"><x-navigation.item label="Dashboard" :href="route('app')" :active="request()->routeIs('app')" /></x-navigation.section>
            @if ($canViewRelations)
                <x-navigation.section title="Relaties"><x-navigation.item label="Alle relaties" :href="route('relations.index')" :active="request()->routeIs('relations.*')" /></x-navigation.section>
            @endif
            <x-navigation.section title="Verkoop">
                @if ($canViewSales)
                    <x-navigation.item label="Offertes" :href="route('sales.quotations.index')" :active="request()->routeIs('sales.quotations.*')" />
                @endif
                @if ($canViewSales)
                    <x-navigation.item label="Orders" :href="route('sales.orders.index')" :active="request()->routeIs('sales.orders.*')" />
                @endif
                @if ($canViewSales)
                    <x-navigation.item label="Facturen" :href="route('sales.invoices.index')" :active="request()->routeIs('sales.invoices.*')" />
                @endif
                @if ($canViewSales)
                    <x-navigation.item label="Creditfacturen" :href="route('sales.credit-invoices.index')" :active="request()->routeIs('sales.credit-invoices.*')" />
                @endif
            </x-navigation.section>
            @if ($canViewPurchasing)
                <x-navigation.section title="Inkoop"><x-navigation.item label="Inkoopfacturen" :href="route('purchasing.invoices.index')" :active="request()->routeIs('purchasing.invoices.*')" /><x-navigation.item label="Creditnota's" :href="route('purchasing.credits.index')" :active="request()->routeIs('purchasing.credits.*')" /></x-navigation.section>
            @endif
            @if($canViewBanking)<x-navigation.section title="Bank"><x-navigation.item label="Betalingen" :href="route('banking.payments.index')" :active="request()->routeIs('banking.payments.*')" /></x-navigation.section>@endif
            <x-navigation.section title="Financieel"><x-navigation.item label="Grootboek" disabled /><x-navigation.item label="Openstaande posten" disabled /><x-navigation.item label="BTW" disabled /><x-navigation.item label="Rapportages" disabled /></x-navigation.section>
            <x-navigation.section title="Beheer"><x-navigation.item label="Administraties" disabled /><x-navigation.item label="Gebruikers & rollen" disabled />@if ($canUpdateAdministrationSettings)<x-navigation.item label="Instellingen" :href="route('settings.administration.edit')" :active="request()->routeIs('settings.administration.*')" /><x-navigation.item label="Dagboeken" :href="route('settings.journals.index')" :active="request()->routeIs('settings.journals.*')" /><x-navigation.item label="Grootboekrekeningen" :href="route('settings.ledger-accounts.index')" :active="request()->routeIs('settings.ledger-accounts.*')" />@endif @if ($canViewAccountingPeriods)<x-navigation.item label="Perioden" :href="route('settings.accounting-periods.index')" :active="request()->routeIs('settings.accounting-periods.*')" />@endif</x-navigation.section>
        </nav>
    </aside>
    <div class="min-w-0">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                <button data-menu-open class="min-h-11 min-w-11 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-700 lg:hidden" aria-controls="primary-navigation" aria-expanded="false"><span class="sr-only">Menu openen</span>☰</button>
                <div class="min-w-0"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">Actieve administratie</p><p class="truncate font-semibold">{{ $administrationContext->administration->name()->toString() }}</p></div>
                <div class="flex items-center gap-2 sm:gap-4"><span class="hidden text-sm text-slate-600 sm:inline">{{ $domainUser->displayName()->toString() }}</span><a href="{{ route('administrations.select') }}" class="min-h-11 rounded-lg px-3 py-2.5 text-sm font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Administratie wisselen</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-blue-700">Uitloggen</button></form></div>
            </div>
        </header>
        <main id="main-content" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            @if (session('status'))<p class="mb-6 rounded-lg bg-emerald-50 p-3 text-emerald-900" role="status">{{ session('status') }}</p>@endif
            @if (session('error'))<p class="mb-6 rounded-lg bg-red-50 p-3 text-red-800" role="alert">{{ session('error') }}</p>@endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
