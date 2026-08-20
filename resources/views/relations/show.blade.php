<x-layouts.app :$domainUser :$administrationContext :$canViewRelations :title="$relation->displayName()->toString()">
    <nav aria-label="Kruimelpad" class="text-sm text-slate-600">
        <a href="{{ route('relations.index') }}" class="font-medium text-blue-700 underline-offset-4 hover:underline focus:ring-2 focus:ring-blue-700">Relaties</a>
        <span aria-hidden="true"> / </span>
        <span aria-current="page">{{ $relation->displayName()->toString() }}</span>
    </nav>

    <header class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-slate-600">{{ $relation->code()->toString() }}</p>
                <h1 class="mt-1 text-2xl font-semibold">{{ $relation->displayName()->toString() }}</h1>
            </div>
            <span @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-emerald-100 text-emerald-900' => $relation->isActive(), 'bg-slate-200 text-slate-800' => ! $relation->isActive()])>{{ $relation->isActive() ? 'Actief' : 'Inactief' }}</span>
        </div>
    </header>

    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" aria-labelledby="basisgegevens-heading">
        <h2 id="basisgegevens-heading" class="text-lg font-semibold">Basisgegevens</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm font-medium text-slate-600">Code</dt><dd class="mt-1">{{ $relation->code()->toString() }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-600">Naam</dt><dd class="mt-1">{{ $relation->displayName()->toString() }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-600">Status</dt><dd class="mt-1">{{ $relation->isActive() ? 'Actief' : 'Inactief' }}</dd></div>
            <div>
                <dt class="text-sm font-medium text-slate-600">Classificatie</dt>
                <dd class="mt-2 flex flex-wrap gap-2">
                    @if ($relation->isCustomer())<span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-900">Klant</span>@endif
                    @if ($relation->isSupplier())<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">Leverancier</span>@endif
                    @if (! $relation->isCustomer() && ! $relation->isSupplier())<span>Geen classificatie</span>@endif
                </dd>
            </div>
        </dl>
    </section>

    <p class="mt-6"><a href="{{ route('relations.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Terug naar relaties</a></p>
</x-layouts.app>
