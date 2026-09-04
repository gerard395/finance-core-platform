<x-layouts.app :domain-user="$domainUser" :administration-context="$administrationContext" title="Bankafschrift importeren">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-2xl font-bold">Bankafschrift importeren</h1><p class="mt-1 text-slate-600">Upload een CAMT.053 .02- of .08-bestand voor een preview.</p></div><a class="font-semibold text-blue-700" href="{{ route('banking.import.batches.index') }}">Eerdere imports</a></div>
    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm">
        <p class="mb-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">Alleen XML, maximaal 5 MB en uitsluitend EUR. ZIP-bestanden worden geweigerd. De preview maakt nog geen duurzame bron- of financiële feiten.</p>
        <form method="POST" action="{{ route('banking.import.preview') }}" enctype="multipart/form-data" class="grid gap-4">@csrf
            <label class="font-semibold">Bankrekening<select name="bank_account_id" required class="mt-1 block w-full rounded-lg border-slate-300"><option value="">Selecteer</option>@foreach($bankAccounts as $account)<option value="{{ $account->id()->toString() }}" @selected(old('bank_account_id')===$account->id()->toString())>{{ $account->label()->value() }} · {{ $account->iban()->value() }}</option>@endforeach</select>@error('bank_account_id')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror</label>
            <label class="font-semibold">CAMT-bestand<input name="file" type="file" accept=".xml,text/xml,application/xml" required class="mt-1 block w-full rounded-lg border border-slate-300 p-3">@error('file')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror</label>
            <button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Preview maken</button>
        </form>
    </section>
</x-layouts.app>
