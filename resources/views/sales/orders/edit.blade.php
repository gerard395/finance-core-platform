<x-layouts.app :$domainUser :$administrationContext :$canViewRelations :$canViewSales title="Order bewerken">
    <h1 class="text-2xl font-bold">Order {{ $order->number()->value() }} bewerken</h1>
    <form method="POST" action="{{ route('sales.orders.update',$order->id()->toString()) }}" class="mt-6 max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm">@csrf @method('PUT')
        <dl class="grid gap-2 text-sm"><div><dt class="font-semibold">Klant</dt><dd>{{ $order->customer()->customerNumber()->toString() }} – {{ $order->customer()->displayName()->toString() }}</dd></div><div><dt class="font-semibold">Valuta</dt><dd>{{ $order->currency()->code() }}</dd></div></dl>
        <label class="block font-medium">Orderdatum<input type="date" name="order_date" required value="{{ old('order_date',$order->orderDate()->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300">@error('order_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <div class="flex gap-3"><button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Wijzigingen opslaan</button>@if($canViewSales)<a class="px-4 py-3 font-semibold text-blue-700" href="{{ route('sales.orders.show',$order->id()->toString()) }}">Annuleren</a>@endif</div>
    </form>
</x-layouts.app>
