<x-layouts.app title="Verkoopfactuur bewerken" :$domainUser :$administrationContext :$canViewRelations :$canViewSales>
    <h1 class="text-2xl font-bold">Factuur {{ $invoice->number()->value() }} bewerken</h1>
    <section class="mt-6 max-w-2xl rounded-xl bg-slate-50 p-4"><p><strong>Klant:</strong> {{ $invoice->customer()->customerNumber()->toString() }} · {{ $invoice->customer()->displayName()->toString() }}</p><p><strong>Valuta:</strong> {{ $invoice->currency()->code() }}</p><p><strong>Bronorder:</strong> {{ $invoice->sourceOrderId()?->toString() ?? 'Geen (directe factuur)' }}</p></section>
    <form method="POST" action="{{ route('sales.invoices.update',$invoice->id()->toString()) }}" class="mt-6 max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm">@csrf @method('PUT')
        <label class="block">Factuurdatum<input type="date" name="invoice_date" required value="{{ old('invoice_date',$invoice->invoiceDate()->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300">@error('invoice_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <label class="block">Vervaldatum<input type="date" name="due_date" required value="{{ old('due_date',$invoice->dueDate()->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border-slate-300">@error('due_date')<span class="text-sm text-red-700">{{ $message }}</span>@enderror</label>
        <div class="flex flex-wrap gap-3"><button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Wijzigingen opslaan</button>@if($canViewSales)<a class="px-4 py-3 font-semibold text-blue-700" href="{{ route('sales.invoices.show',$invoice->id()->toString()) }}">Annuleren</a>@endif</div>
    </form>
</x-layouts.app>
