<x-layouts.app :domain-user="$domainUser" :administration-context="$administrationContext" title="Boekjaar toevoegen">
    <div class="mx-auto max-w-2xl"><p class="text-sm font-semibold text-blue-700">Beheer → Grootboek → Perioden</p><h1 class="text-2xl font-bold">Boekjaar toevoegen</h1>
        <p class="mt-2 text-sm text-slate-600">Perioden worden niet automatisch aangemaakt. Richt deze na het opslaan expliciet in.</p>
        <form method="POST" action="{{ route('settings.accounting-periods.store') }}" class="mt-6 space-y-5 rounded-xl border bg-white p-6">@csrf
            @error('book_year')<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            <div><label for="code" class="font-semibold">Code</label><input id="code" name="code" value="{{ old('code') }}" required maxlength="50" class="mt-2 min-h-11 w-full rounded-lg border px-3">@error('code')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="label" class="font-semibold">Label</label><input id="label" name="label" value="{{ old('label') }}" maxlength="100" class="mt-2 min-h-11 w-full rounded-lg border px-3">@error('label')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="start_date" class="font-semibold">Startdatum</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}" required class="mt-2 min-h-11 w-full rounded-lg border px-3">@error('start_date')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="end_date" class="font-semibold">Einddatum</label><input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" required class="mt-2 min-h-11 w-full rounded-lg border px-3">@error('end_date')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <button class="min-h-11 rounded-lg bg-blue-700 px-5 py-2 text-white">Aanmaken</button>
        </form>
    </div>
</x-layouts.app>
