<x-layouts.app :$domainUser :$administrationContext :$canViewRelations title="Relatie bewerken">
    <header><h1 class="text-2xl font-semibold">Relatie bewerken</h1><p class="mt-2 text-slate-600">Wijzig de basisgegevens van {{ $relation->displayName()->toString() }}.</p></header>

    <form method="POST" action="{{ route('relations.update', $relation->id()->toString()) }}" class="mt-6 max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf
        @method('PUT')
        <div><p class="font-medium">Code</p><p class="mt-1 text-slate-700">{{ $relation->code()->toString() }}</p><p class="mt-1 text-sm text-slate-600">De relatiecode kan na aanmaak niet worden gewijzigd.</p></div>
        <div>
            <label for="name" class="block font-medium">Naam <span aria-hidden="true">*</span></label>
            <input id="name" name="name" value="{{ old('name', $relation->displayName()->toString()) }}" required maxlength="255" @if($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
            @error('name')<p id="name-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="block font-medium">Status <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required @if($errors->has('status')) aria-invalid="true" aria-describedby="status-error" @endif class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                <option value="active" @selected(old('status', $relation->isActive() ? 'active' : 'inactive') === 'active')>Actief</option>
                <option value="inactive" @selected(old('status', $relation->isActive() ? 'active' : 'inactive') === 'inactive')>Inactief</option>
            </select>
            @error('status')<p id="status-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="vat_identification_number" class="block font-medium">BTW-identificatienummer</label>
            <input id="vat_identification_number" name="vat_identification_number" value="{{ old('vat_identification_number', $relation->vatIdentificationNumber()?->toString()) }}" maxlength="32" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
            @error('vat_identification_number')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="fiscal_jurisdiction" class="block font-medium">Fiscale jurisdictie / vestigingsland</label>
            <input id="fiscal_jurisdiction" name="fiscal_jurisdiction" value="{{ old('fiscal_jurisdiction', $relation->fiscalJurisdiction()?->value()) }}" maxlength="2" placeholder="NL" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 uppercase focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
            @error('fiscal_jurisdiction')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="min-h-11 rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Opslaan</button>
            <a href="{{ $canViewRelations ? route('relations.show', $relation->id()->toString()) : route('app') }}" class="inline-flex min-h-11 items-center rounded-lg px-4 font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Annuleren</a>
        </div>
    </form>
</x-layouts.app>
