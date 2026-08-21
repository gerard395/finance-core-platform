<x-layouts.app :$domainUser :$administrationContext :$canViewRelations :$canViewSales title="Nieuwe offerte">
    <h1 class="text-2xl font-bold">Nieuwe offerte</h1>
    <form method="POST" action="{{ route('sales.quotations.store') }}" class="mt-6 max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm">@csrf
        <label class="block font-medium">Klant<select name="customer_id" required class="mt-1 w-full rounded-lg border-slate-300"><option value="">Selecteer een klant</option>@foreach($customers as $customer)<option value="{{ $customer['id'] }}" @selected(old('customer_id')===$customer['id'])>{{ $customer['number'] }} – {{ $customer['name'] }}</option>@endforeach</select>@error('customer_id')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block font-medium">Offertedatum<input type="date" name="quotation_date" required value="{{ old('quotation_date',date('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300">@error('quotation_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block font-medium">Vervaldatum<input type="date" name="expiry_date" value="{{ old('expiry_date') }}" class="mt-1 w-full rounded-lg border-slate-300">@error('expiry_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <p class="text-sm text-slate-600">Valuta: {{ $administrationContext->administration->baseCurrency()->code() }}. Het offertenummer wordt automatisch toegekend.</p>
        <div class="flex gap-3"><button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Offerte aanmaken</button>@if($canViewSales)<a class="px-4 py-3 font-semibold text-blue-700" href="{{ route('sales.quotations.index') }}">Annuleren</a>@endif</div>
    </form>
</x-layouts.app>
