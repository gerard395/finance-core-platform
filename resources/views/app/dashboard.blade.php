<x-layouts.app :$domainUser :$administrationContext :$canViewRelations title="Dashboard">
    <header>
        <h1 class="text-2xl font-semibold">Dashboard</h1>
        <p class="mt-2 text-slate-600">Financieel overzicht voor {{ $administrationContext->administration->name()->toString() }}.</p>
        <p class="mt-1 text-sm text-slate-500">Periode: {{ $overview->periodStart()->value()->format('d-m-Y') }} t/m {{ $overview->periodEnd()->value()->format('d-m-Y') }}</p>
    </header>
    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Dashboardoverzicht">
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold">Omzet deze maand</h2>
            <p class="mt-4 text-2xl font-semibold tabular-nums">{{ $moneyFormatter->format($overview->revenue()) }}</p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold">Openstaande debiteuren</h2>
            <p class="mt-4 text-2xl font-semibold tabular-nums">{{ $moneyFormatter->format($overview->outstandingReceivables()) }}</p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold">Openstaande crediteuren</h2>
            <p class="mt-4 text-2xl font-semibold tabular-nums">{{ $moneyFormatter->format($overview->outstandingPayables()) }}</p>
        </article>
        <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold">BTW-positie</h2>
            <p class="mt-4 text-2xl font-semibold tabular-nums">{{ $moneyFormatter->format($overview->vatPosition()) }}</p>
        </article>
    </section>
</x-layouts.app>
