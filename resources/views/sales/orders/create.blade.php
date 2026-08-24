<x-layouts.app :$domainUser :$administrationContext :$canViewRelations :$canViewSales title="Nieuwe order">
    <h1 class="text-2xl font-bold">Nieuwe order</h1>
    <form method="POST" action="{{ route('sales.orders.store') }}" class="mt-6 max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm">@csrf
        <label class="block font-medium">Klant<select name="customer_id" required class="mt-1 w-full rounded-lg border-slate-300"><option value="">Selecteer een klant</option>@foreach($customers as $customer)<option value="{{ $customer['id'] }}" @selected(old('customer_id')===$customer['id'])>{{ $customer['number'] }} – {{ $customer['name'] }}</option>@endforeach</select>@error('customer_id')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block font-medium">Orderdatum<input type="date" name="order_date" required value="{{ old('order_date',date('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300">@error('order_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <p class="text-sm text-slate-600">Valuta: {{ $administrationContext->administration->baseCurrency()->code() }}. Het ordernummer wordt automatisch toegekend.</p>
        <div class="flex gap-3"><button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Order aanmaken</button>@if($canViewSales)<a class="px-4 py-3 font-semibold text-blue-700" href="{{ route('sales.orders.index') }}">Annuleren</a>@endif</div>
    </form>
</x-layouts.app>
