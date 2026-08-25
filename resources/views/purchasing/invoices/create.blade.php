<x-layouts.app title="Nieuwe inkoopfactuur" :$domainUser :$administrationContext>
    <h1 class="text-2xl font-bold">Nieuwe inkoopfactuur</h1><p class="text-slate-600">Neem het adres en de gegevens exact over van het ontvangen document.</p>
    <form method="POST" action="{{ route('purchasing.invoices.store') }}" class="mt-6 space-y-6">@csrf @include('purchasing.invoices.fields',['invoice'=>null])<button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Draft opslaan</button></form>
</x-layouts.app>
