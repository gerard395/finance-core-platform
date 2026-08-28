<x-layouts.app title="Creditnota bewerken" :$domainUser :$administrationContext>
<h1 class="text-2xl font-bold">Creditnota bewerken</h1><form method="POST" action="{{ route('purchasing.credits.update',$credit->id()->toString()) }}" class="mt-6 space-y-6">@csrf @method('PUT') @include('purchasing.credits.fields')<button class="rounded bg-blue-700 px-4 py-3 font-semibold text-white">Wijzigingen opslaan</button></form>
</x-layouts.app>
