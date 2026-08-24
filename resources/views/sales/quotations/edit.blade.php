<x-layouts.app :$domainUser :$administrationContext :$canViewRelations :$canViewSales title="Offerte bewerken">
    <h1 class="text-2xl font-bold">Offerte {{ $quotation->number()->value() }} bewerken</h1>
    <form method="POST" action="{{ route('sales.quotations.update',$quotation->id()->toString()) }}" class="mt-6 max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm">@csrf @method('PUT')
        <dl class="grid gap-2 text-sm"><div><dt class="font-semibold">Klant</dt><dd>{{ $quotation->customer()->customerNumber()->toString() }} – {{ $quotation->customer()->displayName()->toString() }}</dd></div><div><dt class="font-semibold">Valuta</dt><dd>{{ $quotation->currency()->code() }}</dd></div></dl>
        <label class="block font-medium">Offertedatum<input type="date" name="quotation_date" required value="{{ old('quotation_date',$quotation->quotationDate()->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300">@error('quotation_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block font-medium">Vervaldatum<input type="date" name="expiry_date" value="{{ old('expiry_date',$quotation->expiryDate()?->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300">@error('expiry_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <div class="flex gap-3"><button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Wijzigingen opslaan</button>@if($canViewSales)<a class="px-4 py-3 font-semibold text-blue-700" href="{{ route('sales.quotations.show',$quotation->id()->toString()) }}">Annuleren</a>@endif</div>
    </form>
</x-layouts.app>
