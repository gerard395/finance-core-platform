<x-layouts.app title="Inkoopfactuur bewerken" :$domainUser :$administrationContext>
    <h1 class="text-2xl font-bold">Inkoopfactuur bewerken</h1>
    <form method="POST" action="{{ route('purchasing.invoices.update',$invoice->id()->toString()) }}" class="mt-6 space-y-6">@csrf @method('PUT') @include('purchasing.invoices.fields',['invoice'=>$invoice])<button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Wijzigingen opslaan</button></form>
</x-layouts.app>
