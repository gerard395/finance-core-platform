<x-layouts.app :domain-user="$domainUser" :administration-context="$administrationContext" title="Instellingen">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <p class="text-sm font-semibold text-blue-700">Beheer</p>
            <h1 class="text-2xl font-bold tracking-tight">Instellingen</h1>
            <p class="mt-2 text-sm text-slate-600">Wijzig de basisgegevens van de actieve administratie.</p>
        </div>

        <form method="POST" action="{{ route('settings.administration.update') }}" class="space-y-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="block text-sm font-semibold">Naam</label>
                <input id="name" name="name" value="{{ old('name', $settings->name) }}" required maxlength="255" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @error('name')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="block text-sm font-semibold">Omschrijving</label>
                <textarea id="description" name="description" rows="5" maxlength="1000" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">{{ old('description', $settings->description) }}</textarea>
                @error('description')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="vat_identification_number" class="block text-sm font-semibold">BTW-identificatienummer</label>
                <input id="vat_identification_number" name="vat_identification_number" value="{{ old('vat_identification_number', $settings->vatIdentificationNumber?->toString()) }}" maxlength="32" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @error('vat_identification_number')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="fiscal_jurisdiction" class="block text-sm font-semibold">Fiscale jurisdictie / vestigingsland</label>
                <input id="fiscal_jurisdiction" name="fiscal_jurisdiction" value="{{ old('fiscal_jurisdiction', $settings->fiscalJurisdiction?->value()) }}" maxlength="2" placeholder="NL" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 uppercase focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @error('fiscal_jurisdiction')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="min-h-11 rounded-lg bg-blue-700 px-5 py-2.5 font-semibold text-white hover:bg-blue-800 focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Opslaan</button>
            </div>
        </form>
    </div>
</x-layouts.app>
