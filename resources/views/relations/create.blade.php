<x-layouts.app :$domainUser :$administrationContext :$canViewRelations title="Nieuwe relatie">
    <header><h1 class="text-2xl font-semibold">Nieuwe relatie</h1><p class="mt-2 text-slate-600">Voeg een relatie toe aan {{ $administrationContext->administration->name()->toString() }}.</p></header>

    <form method="POST" action="{{ route('relations.store') }}" class="mt-6 max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf
        <div>
            <label for="code" class="block font-medium">Code <span aria-hidden="true">*</span></label>
            <input id="code" name="code" value="{{ old('code') }}" required maxlength="32" aria-describedby="code-help @error('code') code-error @enderror" @if($errors->has('code')) aria-invalid="true" @endif class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
            <p id="code-help" class="mt-1 text-sm text-slate-600">2 tot 32 letters, cijfers, streepjes of liggende streepjes.</p>
            @error('code')<p id="code-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="name" class="block font-medium">Naam <span aria-hidden="true">*</span></label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" @if($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
            @error('name')<p id="name-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="vat_identification_number" class="block font-medium">BTW-identificatienummer</label>
            <input id="vat_identification_number" name="vat_identification_number" value="{{ old('vat_identification_number') }}" maxlength="32" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
            @error('vat_identification_number')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="fiscal_jurisdiction" class="block font-medium">Fiscale jurisdictie / vestigingsland</label>
            <input id="fiscal_jurisdiction" name="fiscal_jurisdiction" value="{{ old('fiscal_jurisdiction') }}" maxlength="2" placeholder="NL" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 uppercase focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
            @error('fiscal_jurisdiction')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="min-h-11 rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Opslaan</button>
            <a href="{{ $canViewRelations ? route('relations.index') : route('app') }}" class="inline-flex min-h-11 items-center rounded-lg px-4 font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Annuleren</a>
        </div>
    </form>
</x-layouts.app>
