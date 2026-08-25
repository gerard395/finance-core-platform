<x-layouts.app :$domainUser :$administrationContext :$canViewRelations :title="$relation->displayName()->toString()">
    <nav aria-label="Kruimelpad" class="text-sm text-slate-600">
        <a href="{{ route('relations.index') }}" class="font-medium text-blue-700 underline-offset-4 hover:underline focus:ring-2 focus:ring-blue-700">Relaties</a>
        <span aria-hidden="true"> / </span>
        <span aria-current="page">{{ $relation->displayName()->toString() }}</span>
    </nav>

    <header class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-slate-600">{{ $relation->code()->toString() }}</p>
                <h1 class="mt-1 text-2xl font-semibold">{{ $relation->displayName()->toString() }}</h1>
            </div>
            <span @class(['rounded-full px-3 py-1 text-sm font-medium', 'bg-emerald-100 text-emerald-900' => $relation->isActive(), 'bg-slate-200 text-slate-800' => ! $relation->isActive()])>{{ $relation->isActive() ? 'Actief' : 'Inactief' }}</span>
        </div>
        @if ($canUpdateRelations)<p class="mt-5"><a href="{{ route('relations.edit', $relation->id()->toString()) }}" class="inline-flex min-h-11 items-center rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Bewerken</a></p>@endif
    </header>

    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" aria-labelledby="basisgegevens-heading">
        <h2 id="basisgegevens-heading" class="text-lg font-semibold">Basisgegevens</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm font-medium text-slate-600">Code</dt><dd class="mt-1">{{ $relation->code()->toString() }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-600">Naam</dt><dd class="mt-1">{{ $relation->displayName()->toString() }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-600">Status</dt><dd class="mt-1">{{ $relation->isActive() ? 'Actief' : 'Inactief' }}</dd></div>
            <div>
                <dt class="text-sm font-medium text-slate-600">Classificatie</dt>
                <dd class="mt-2 flex flex-wrap gap-2">
                    @if ($relation->isCustomer())<span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-900">Klant</span>@endif
                    @if ($relation->isSupplier())<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">Leverancier</span>@endif
                    @if (! $relation->isCustomer() && ! $relation->isSupplier())<span>Geen classificatie</span>@endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" aria-labelledby="contactpersonen-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="contactpersonen-heading" class="text-lg font-semibold">Contactpersonen</h2>
            @if ($canUpdateRelations)<a href="{{ route('relations.contacts.create', $relation->id()->toString()) }}" class="inline-flex min-h-11 items-center rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Contactpersoon toevoegen</a>@endif
        </div>
        @if ($contacts === [])
            <p class="mt-4 text-slate-600">Nog geen contactpersonen.</p>
        @else
            <div class="mt-4 hidden overflow-x-auto sm:block">
                <table class="w-full text-left"><thead><tr class="border-b border-slate-200"><th class="px-3 py-2">Naam</th><th class="px-3 py-2">E-mail</th><th class="px-3 py-2">Telefoon</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"><span class="sr-only">Acties</span></th></tr></thead>
                    <tbody>@foreach ($contacts as $contact)<tr class="border-b border-slate-100"><td class="px-3 py-3"><a class="font-medium text-blue-700 hover:underline focus:ring-2 focus:ring-blue-700" href="{{ route('relations.contacts.show', [$relation->id()->toString(), $contact->id->toString()]) }}">{{ $contact->name->toString() }}</a></td><td class="px-3 py-3">{{ $contact->emailAddress?->toString() ?? '—' }}</td><td class="px-3 py-3">{{ $contact->phoneNumber?->toString() ?? '—' }}</td><td class="px-3 py-3">{{ $contact->status->value === 'active' ? 'Actief' : 'Inactief' }}</td><td class="px-3 py-3">@if ($canUpdateRelations)<a class="font-medium text-blue-700 hover:underline focus:ring-2 focus:ring-blue-700" href="{{ route('relations.contacts.edit', [$relation->id()->toString(), $contact->id->toString()]) }}">Bewerken</a>@endif</td></tr>@endforeach</tbody>
                </table>
            </div>
            <div class="mt-4 grid gap-3 sm:hidden">@foreach ($contacts as $contact)<article class="rounded-lg border border-slate-200 p-4"><h3 class="font-semibold"><a class="text-blue-700 hover:underline focus:ring-2 focus:ring-blue-700" href="{{ route('relations.contacts.show', [$relation->id()->toString(), $contact->id->toString()]) }}">{{ $contact->name->toString() }}</a></h3><p class="mt-2 text-sm">{{ $contact->emailAddress?->toString() ?? 'Geen e-mail' }}</p><p class="mt-1 text-sm">{{ $contact->phoneNumber?->toString() ?? 'Geen telefoon' }}</p><p class="mt-2 font-medium">{{ $contact->status->value === 'active' ? 'Actief' : 'Inactief' }}</p>@if ($canUpdateRelations)<a class="mt-3 inline-flex min-h-11 items-center font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700" href="{{ route('relations.contacts.edit', [$relation->id()->toString(), $contact->id->toString()]) }}">Bewerken</a>@endif</article>@endforeach</div>
        @endif
    </section>

    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" aria-labelledby="documentontvangers-heading">
        <h2 id="documentontvangers-heading" class="text-lg font-semibold">Documentontvangers</h2>
        <p class="mt-1 text-sm text-slate-600">Kies per documentdoel expliciet één actieve contactpersoon met e-mailadres. Er is geen automatische fallback.</p>
        @error('recipient_preference')<p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach($recipientPurposes as $purpose)
                @php($label = match($purpose) { \App\Application\Sales\SalesDocumentRecipientPurpose::Quotation => 'Offerte-ontvanger', \App\Application\Sales\SalesDocumentRecipientPurpose::SalesInvoice => 'Factuur-ontvanger', \App\Application\Sales\SalesDocumentRecipientPurpose::SalesCreditInvoice => 'Creditfactuur-ontvanger' })
                @php($recipient = $documentRecipients[$purpose->value])
                <article class="rounded-lg border border-slate-200 p-4"><h3 class="font-semibold">{{ $label }}</h3>
                    <p class="mt-2 text-sm">@if($recipient->status === \App\Application\Sales\SalesDocumentRecipientStatus::Success){{ $recipient->displayName->toString() }}<br>{{ $recipient->emailAddress->toString() }}@elseif($recipient->status === \App\Application\Sales\SalesDocumentRecipientStatus::Invalid)<span class="text-red-700">Voorkeur ongeldig: contact is inactief of heeft geen e-mail.</span>@else<span class="text-slate-600">Niet ingesteld.</span>@endif</p>
                    @if($canUpdateRelations)
                        <form method="POST" action="{{ route('relations.document-recipients.store', $relation->id()->toString()) }}" class="mt-3 space-y-2">@csrf<input type="hidden" name="purpose" value="{{ $purpose->value }}"><label class="block text-sm font-medium">Contactpersoon<select name="contact_id" required class="mt-1 w-full rounded-lg border-slate-300"><option value="">Selecteer</option>@foreach($contacts as $contact)@if($contact->status->value === 'active' && $contact->emailAddress)<option value="{{ $contact->id->toString() }}">{{ $contact->name->toString() }} – {{ $contact->emailAddress->toString() }}</option>@endif @endforeach</select></label><button class="min-h-11 rounded-lg bg-blue-700 px-3 font-semibold text-white">Opslaan</button></form>
                        @if($recipient->status !== \App\Application\Sales\SalesDocumentRecipientStatus::Missing)<form method="POST" action="{{ route('relations.document-recipients.destroy', [$relation->id()->toString(), $purpose->value]) }}" class="mt-2">@csrf @method('DELETE')<button class="min-h-11 font-semibold text-red-700">Voorkeur wissen</button></form>@endif
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" aria-labelledby="adressen-heading">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="adressen-heading" class="text-lg font-semibold">Adressen</h2>@if($canUpdateRelations)<a href="{{ route('relations.addresses.create', $relation->id()->toString()) }}" class="inline-flex min-h-11 items-center rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Adres toevoegen</a>@endif</div>
        @if($addresses === [])<p class="mt-4 text-slate-600">Nog geen adressen.</p>@else
            <div class="mt-4 grid gap-4 md:grid-cols-2">@foreach($addresses as $address)<article class="rounded-lg border border-slate-200 p-4"><div class="flex items-start justify-between gap-3"><h3 class="font-semibold"><a class="text-blue-700 hover:underline focus:ring-2 focus:ring-blue-700" href="{{ route('relations.addresses.show', [$relation->id()->toString(), $address->id->toString()]) }}">{{ $addressTypePresenter::label($address->type) }}</a></h3><span class="text-sm font-medium">{{ $address->active ? 'Actief' : 'Inactief' }}</span></div><p class="mt-2">{{ $address->addressLine->value() }}@if($address->addressLine2)<br>{{ $address->addressLine2->value() }}@endif<br>{{ $address->postalCode->value() }} {{ $address->city->value() }}<br>{{ $address->countryCode->value() }}</p>@if($canUpdateRelations)<a href="{{ route('relations.addresses.edit', [$relation->id()->toString(), $address->id->toString()]) }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Bewerken</a>@endif</article>@endforeach</div>
        @endif
    </section>

    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" aria-labelledby="bankrekeningen-heading">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="bankrekeningen-heading" class="text-lg font-semibold">Bankrekeningen</h2>@if($canUpdateRelations)<a href="{{ route('relations.bank-accounts.create', $relation->id()->toString()) }}" class="inline-flex min-h-11 items-center rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Bankrekening toevoegen</a>@endif</div>
        @if($bankAccounts === [])<p class="mt-4 text-slate-600">Nog geen bankrekeningen.</p>@else<div class="mt-4 grid gap-4 md:grid-cols-2">@foreach($bankAccounts as $bankAccount)<article class="rounded-lg border border-slate-200 p-4"><div class="flex items-start justify-between gap-3"><h3 class="font-semibold"><a class="text-blue-700 hover:underline focus:ring-2 focus:ring-blue-700" href="{{ route('relations.bank-accounts.show', [$relation->id()->toString(), $bankAccount->id->toString()]) }}">{{ $bankAccount->accountName->value() }}</a></h3><span class="text-sm font-medium">{{ $bankAccount->status->value === 'active' ? 'Actief' : 'Inactief' }}</span></div><p class="mt-2"><span class="font-medium">IBAN:</span> {{ $bankAccount->iban->value() }}</p><p class="mt-1"><span class="font-medium">BIC:</span> {{ $bankAccount->bic?->value() ?? 'Niet opgegeven' }}</p>@if($canUpdateRelations)<a href="{{ route('relations.bank-accounts.edit', [$relation->id()->toString(), $bankAccount->id->toString()]) }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Bewerken</a>@endif</article>@endforeach</div>@endif
    </section>

    @if ($canClassifyCustomer || $canClassifySupplier)
        <section class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200" aria-labelledby="classificaties-heading">
            <h2 id="classificaties-heading" class="text-lg font-semibold">Classificaties beheren</h2>
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                @if ($canClassifyCustomer)
                    <div><h3 class="font-semibold">Klant</h3><p class="mt-1 text-sm text-slate-600">{{ $relation->isCustomer() ? 'De klantclassificatie is actief.' : 'De klantclassificatie is niet actief.' }}</p>
                        @if ($relation->isCustomer())
                            <form method="POST" action="{{ route('relations.customer.destroy', $relation->id()->toString()) }}" class="mt-3">@csrf @method('DELETE')<button class="min-h-11 rounded-lg border border-red-300 px-4 font-semibold text-red-800 focus:ring-2 focus:ring-red-700">Klantclassificatie verwijderen</button></form>
                        @else
                            <form method="POST" action="{{ route('relations.customer.store', $relation->id()->toString()) }}" class="mt-3">@csrf<button class="min-h-11 rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Als klant classificeren</button></form>
                        @endif
                    </div>
                @endif
                @if ($canClassifySupplier)
                    <div><h3 class="font-semibold">Leverancier</h3><p class="mt-1 text-sm text-slate-600">{{ $relation->isSupplier() ? 'De leveranciersclassificatie is actief.' : 'De leveranciersclassificatie is niet actief.' }}</p>
                        @if ($relation->isSupplier())
                            <form method="POST" action="{{ route('relations.supplier.destroy', $relation->id()->toString()) }}" class="mt-3">@csrf @method('DELETE')<button class="min-h-11 rounded-lg border border-red-300 px-4 font-semibold text-red-800 focus:ring-2 focus:ring-red-700">Leveranciersclassificatie verwijderen</button></form>
                        @else
                            <form method="POST" action="{{ route('relations.supplier.store', $relation->id()->toString()) }}" class="mt-3">@csrf<button class="min-h-11 rounded-lg bg-blue-700 px-4 font-semibold text-white focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Als leverancier classificeren</button></form>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif

    <p class="mt-6"><a href="{{ route('relations.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-3 font-semibold text-blue-700 focus:ring-2 focus:ring-blue-700">Terug naar relaties</a></p>
</x-layouts.app>
