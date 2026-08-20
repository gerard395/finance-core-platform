<x-layouts.app :$domainUser :$administrationContext :$canViewRelations title="Relaties">
    <header>
        <h1 class="text-2xl font-semibold">Relaties</h1>
        <p class="mt-2 text-slate-600">Bekijk en filter de relaties van {{ $administrationContext->administration->name()->toString() }}.</p>
    </header>

    <form method="GET" action="{{ route('relations.index') }}" class="mt-6 grid gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-2 xl:grid-cols-7">
        <div class="md:col-span-2 xl:col-span-2">
            <label for="q" class="block text-sm font-medium">Zoeken</label>
            <input id="q" name="q" value="{{ $query->searchTerm() }}" placeholder="Zoek op code of naam" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
        </div>
        <div>
            <label for="classification" class="block text-sm font-medium">Classificatie</label>
            <select id="classification" name="classification" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @foreach (['all' => 'Alle', 'customer' => 'Klant', 'supplier' => 'Leverancier', 'both' => 'Klant én leverancier', 'neither' => 'Geen classificatie'] as $value => $label)
                    <option value="{{ $value }}" @selected($query->classification()->value === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="block text-sm font-medium">Status</label>
            <select id="status" name="status" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @foreach (['all' => 'Alle', 'active' => 'Actief', 'inactive' => 'Inactief'] as $value => $label)
                    <option value="{{ $value }}" @selected($query->status()->value === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="sort" class="block text-sm font-medium">Sorteren op</label>
            <select id="sort" name="sort" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @foreach (['display_name' => 'Naam', 'code' => 'Code', 'status' => 'Status'] as $value => $label)
                    <option value="{{ $value }}" @selected($query->sortField()->value === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="direction" class="block text-sm font-medium">Sorteerrichting</label>
            <select id="direction" name="direction" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                <option value="asc" @selected($query->sortDirection()->value === 'asc')>Oplopend</option>
                <option value="desc" @selected($query->sortDirection()->value === 'desc')>Aflopend</option>
            </select>
        </div>
        <div>
            <label for="per_page" class="block text-sm font-medium">Per pagina</label>
            <select id="per_page" name="per_page" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @foreach ([25, 50, 100] as $value)
                    <option value="{{ $value }}" @selected($query->perPage() === $value)>{{ $value }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2 md:col-span-2 xl:col-span-7">
            <button class="min-h-11 rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Toepassen</button>
            <a href="{{ route('relations.index') }}" class="min-h-11 rounded-lg px-4 py-2.5 font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Filters wissen</a>
        </div>
    </form>

    @if ($relations->items() === [])
        <section class="mt-6 rounded-xl bg-white p-6 text-slate-600 shadow-sm ring-1 ring-slate-200">
            <p>{{ $hasActiveFilters ? 'Geen relaties gevonden voor deze zoekopdracht.' : 'Nog geen relaties.' }}</p>
        </section>
    @else
        <div class="mt-6 hidden overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200 md:block">
            <table class="min-w-full border-collapse text-left text-sm">
                <caption class="sr-only">Relaties, gesorteerd op {{ $query->sortField()->value }} {{ $query->sortDirection()->value }}</caption>
                <thead class="border-b border-slate-200 bg-slate-50"><tr><th scope="col" class="px-4 py-3 font-semibold">Code</th><th scope="col" class="px-4 py-3 font-semibold">Naam</th><th scope="col" class="px-4 py-3 font-semibold">Classificatie</th><th scope="col" class="px-4 py-3 font-semibold">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                @foreach ($relations->items() as $relation)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium">{{ $relation->code()->toString() }}</td>
                        <td class="px-4 py-3"><a href="{{ route('relations.show', $relation->id()->toString()) }}" aria-label="Bekijk {{ $relation->displayName()->toString() }}" class="font-medium text-blue-700 underline-offset-4 hover:underline focus:ring-2 focus:ring-blue-700">{{ $relation->displayName()->toString() }}</a></td>
                        <td class="px-4 py-3"><div class="flex flex-wrap gap-2">@if ($relation->isCustomer())<span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-900">Klant</span>@endif @if ($relation->isSupplier())<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">Leverancier</span>@endif @if (! $relation->isCustomer() && ! $relation->isSupplier())<span aria-label="Geen classificatie">—</span>@endif</div></td>
                        <td class="px-4 py-3"><span @class(['rounded-full px-2 py-1 text-xs font-medium', 'bg-emerald-100 text-emerald-900' => $relation->isActive(), 'bg-slate-200 text-slate-800' => ! $relation->isActive()])>{{ $relation->isActive() ? 'Actief' : 'Inactief' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 space-y-3 md:hidden" aria-label="Relaties">
            @foreach ($relations->items() as $relation)
                <article class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <h2 class="font-semibold">{{ $relation->displayName()->toString() }}</h2>
                    <p class="mt-1 text-sm text-slate-600">Code: {{ $relation->code()->toString() }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">@if ($relation->isCustomer())<span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-900">Klant</span>@endif @if ($relation->isSupplier())<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">Leverancier</span>@endif @if (! $relation->isCustomer() && ! $relation->isSupplier())<span class="text-sm text-slate-500">Geen classificatie</span>@endif <span @class(['rounded-full px-2 py-1 text-xs font-medium', 'bg-emerald-100 text-emerald-900' => $relation->isActive(), 'bg-slate-200 text-slate-800' => ! $relation->isActive()])>{{ $relation->isActive() ? 'Actief' : 'Inactief' }}</span></div>
                    <a href="{{ route('relations.show', $relation->id()->toString()) }}" aria-label="Bekijk {{ $relation->displayName()->toString() }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg font-semibold text-blue-700 underline-offset-4 hover:underline focus:ring-2 focus:ring-blue-700">Bekijken</a>
                </article>
            @endforeach
        </div>
    @endif

    <nav class="mt-6 flex flex-wrap items-center justify-between gap-4" aria-label="Paginering">
        <p class="text-sm text-slate-600">Pagina {{ $relations->page() }} van {{ $relations->lastPage() }} · {{ $relations->total() }} resultaten</p>
        <div class="flex gap-2">
            @if ($relations->page() > 1)<a rel="prev" href="{{ route('relations.index', [...$queryParameters, 'page' => $relations->page() - 1]) }}" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 font-semibold focus:ring-2 focus:ring-blue-700">Vorige</a>@endif
            @if ($relations->hasNextPage())<a rel="next" href="{{ route('relations.index', [...$queryParameters, 'page' => $relations->page() + 1]) }}" class="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 font-semibold focus:ring-2 focus:ring-blue-700">Volgende</a>@endif
        </div>
    </nav>
</x-layouts.app>
