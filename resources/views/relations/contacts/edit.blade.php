<x-layouts.app :$domainUser :$administrationContext :$canViewRelations title="Contactpersoon bewerken">
    <nav aria-label="Kruimelpad" class="text-sm text-slate-600">@if($canViewRelations)<a href="{{ route('relations.show', $relation->id()->toString()) }}" class="font-medium text-blue-700 hover:underline focus:ring-2 focus:ring-blue-700">{{ $relation->displayName()->toString() }}</a><span aria-hidden="true"> / </span>@endif<span aria-current="page">Contactpersoon bewerken</span></nav>
    <section class="mt-4 max-w-2xl rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h1 class="text-2xl font-semibold">Contactpersoon bewerken</h1>
        <form method="POST" action="{{ route('relations.contacts.update', [$relation->id()->toString(), $contact->id->toString()]) }}" class="mt-6 space-y-5">@csrf @method('PUT')
            @include('relations.contacts.fields')
            <div class="flex flex-wrap gap-3"><button class="min-h-11 rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Wijzigingen opslaan</button><a href="{{ $canViewRelations ? route('relations.contacts.show', [$relation->id()->toString(), $contact->id->toString()]) : route('app') }}" class="inline-flex min-h-11 items-center rounded-lg px-4 font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Annuleren</a></div>
        </form>
    </section>
</x-layouts.app>
