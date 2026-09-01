<x-layouts.app :domain-user="$domainUser" :administration-context="$administrationContext" title="Boekjaarlabel wijzigen">
    <div class="mx-auto max-w-2xl"><p class="text-sm font-semibold text-blue-700">Beheer → Grootboek → Perioden</p><h1 class="text-2xl font-bold">Label van {{ $year->code() }} wijzigen</h1><p class="mt-2 text-sm text-slate-600">Code en start- en einddatum zijn immutable.</p>
        <form method="POST" action="{{ route('settings.accounting-periods.update', $year->id()->toString()) }}" class="mt-6 space-y-5 rounded-xl border bg-white p-6">@csrf @method('PUT')
            <div><label for="label" class="font-semibold">Label</label><input id="label" name="label" value="{{ old('label', $year->label()) }}" maxlength="100" class="mt-2 min-h-11 w-full rounded-lg border px-3">@error('label')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <button class="min-h-11 rounded-lg bg-blue-700 px-5 py-2 text-white">Opslaan</button>
        </form>
    </div>
</x-layouts.app>
