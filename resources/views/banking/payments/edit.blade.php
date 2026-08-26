<x-layouts.app title="Bankbetaling bewerken" :$domainUser :$administrationContext>
<h1 class="text-2xl font-bold">Bankbetaling bewerken</h1>
<form data-banking-payment-form method="POST" action="{{ route('banking.payments.update',$detail->transaction->id()->toString()) }}" class="mt-6 space-y-6">@csrf @method('PUT') @include('banking.payments.fields')<button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">Wijzigingen opslaan</button></form>
</x-layouts.app>
