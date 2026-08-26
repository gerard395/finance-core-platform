<x-layouts.app title="Nieuwe bankbetaling" :$domainUser :$administrationContext>
<h1 class="text-2xl font-bold">Nieuwe bankbetaling</h1><p class="text-slate-600">Leg een klantontvangst of leveranciersbetaling vast als Draft.</p>
<form method="POST" action="{{ route('banking.payments.store') }}" class="mt-6 space-y-6">@csrf @include('banking.payments.fields',['detail'=>null])<button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Draft opslaan</button></form>
</x-layouts.app>
