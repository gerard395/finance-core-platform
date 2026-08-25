<x-layouts.app :domain-user="$domainUser" :administration-context="$administrationContext" title="Instellingen">
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <p class="text-sm font-semibold text-blue-700">Beheer</p>
            <h1 class="text-2xl font-bold tracking-tight">Instellingen</h1>
            <p class="mt-2 text-sm text-slate-600">Wijzig de basisgegevens van de actieve administratie.</p>
        </div>

        <form method="POST" action="{{ route('settings.administration.update') }}" class="space-y-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="block text-sm font-semibold">Naam</label>
                <input id="name" name="name" value="{{ old('name', $settings->name) }}" required maxlength="255" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @error('name')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="block text-sm font-semibold">Omschrijving</label>
                <textarea id="description" name="description" rows="5" maxlength="1000" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">{{ old('description', $settings->description) }}</textarea>
                @error('description')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="vat_identification_number" class="block text-sm font-semibold">BTW-identificatienummer</label>
                <input id="vat_identification_number" name="vat_identification_number" value="{{ old('vat_identification_number', $settings->vatIdentificationNumber?->toString()) }}" maxlength="32" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @error('vat_identification_number')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="fiscal_jurisdiction" class="block text-sm font-semibold">Fiscale jurisdictie / vestigingsland</label>
                <input id="fiscal_jurisdiction" name="fiscal_jurisdiction" value="{{ old('fiscal_jurisdiction', $settings->fiscalJurisdiction?->value()) }}" maxlength="2" placeholder="NL" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 uppercase focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                @error('fiscal_jurisdiction')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="min-h-11 rounded-lg bg-blue-700 px-5 py-2.5 font-semibold text-white hover:bg-blue-800 focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Opslaan</button>
            </div>
        </form>

        <form method="POST" action="{{ route('settings.administration.document-delivery.update') }}" class="mt-8 space-y-7 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf @method('PUT')
            <section><h2 class="text-lg font-bold">Documentgegevens</h2><p class="mt-1 text-sm text-slate-600">Juridische afzendergegevens voor offertes, facturen en creditfacturen.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-semibold">Handelsnaam<input name="display_name" value="{{ old('display_name', $documentSettings?->displayName) }}" maxlength="255" class="mt-2 w-full rounded-lg border-slate-300">@error('display_name')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Juridische naam<input name="legal_name" value="{{ old('legal_name', $documentSettings?->legalName) }}" maxlength="255" class="mt-2 w-full rounded-lg border-slate-300">@error('legal_name')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">KVK-/registratienummer<input name="registration_number" value="{{ old('registration_number', $documentSettings?->registrationNumber) }}" maxlength="64" class="mt-2 w-full rounded-lg border-slate-300">@error('registration_number')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Zakelijk e-mailadres<input type="email" name="business_email" value="{{ old('business_email', $documentSettings?->businessEmail?->value()) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('business_email')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Adresregel 1<input name="address_line_1" value="{{ old('address_line_1', $documentSettings?->addressLine1?->value()) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('address_line_1')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Adresregel 2<input name="address_line_2" value="{{ old('address_line_2', $documentSettings?->addressLine2?->value()) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('address_line_2')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Postcode<input name="postal_code" value="{{ old('postal_code', $documentSettings?->postalCode?->value()) }}" maxlength="16" class="mt-2 w-full rounded-lg border-slate-300">@error('postal_code')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Plaats<input name="city" value="{{ old('city', $documentSettings?->city?->value()) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('city')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Landcode<input name="country_code" value="{{ old('country_code', $documentSettings?->countryCode?->value()) }}" maxlength="2" class="mt-2 w-full rounded-lg border-slate-300 uppercase">@error('country_code')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Zakelijk telefoonnummer<input name="business_phone" value="{{ old('business_phone', $documentSettings?->businessPhone) }}" maxlength="32" class="mt-2 w-full rounded-lg border-slate-300">@error('business_phone')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="block text-sm font-semibold">Website<input type="url" name="website" value="{{ old('website', $documentSettings?->website) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('website')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                </div>
            </section>
            <section><h2 class="text-lg font-bold">Betalingsgegevens</h2><p class="mt-1 text-sm text-slate-600">Het factuurnummer wordt als menselijke betalingsreferentie gebruikt; dit is geen automatische bankmatching.</p><div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Rekeninghouder<input name="account_holder" value="{{ old('account_holder', $documentSettings?->accountHolder) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('account_holder')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-semibold">IBAN<input name="iban" value="{{ old('iban', $documentSettings?->iban?->value()) }}" maxlength="34" class="mt-2 w-full rounded-lg border-slate-300 uppercase">@error('iban')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-semibold">BIC (optioneel)<input name="bic" value="{{ old('bic', $documentSettings?->bic?->value()) }}" maxlength="11" class="mt-2 w-full rounded-lg border-slate-300 uppercase">@error('bic')<span class="text-red-700">{{ $message }}</span>@enderror</label>
            </div></section>
            <section><h2 class="text-lg font-bold">E-mailafzender</h2><p class="mt-1 text-sm text-slate-600">Businessidentiteit voor e-mail; technische transportcredentials blijven installation-level.</p><div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Afzendernaam<input name="sender_name" value="{{ old('sender_name', $documentSettings?->senderName) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('sender_name')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-semibold">Afzender-e-mail<input type="email" name="sender_email" value="{{ old('sender_email', $documentSettings?->senderEmail?->value()) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('sender_email')<span class="text-red-700">{{ $message }}</span>@enderror</label>
                <label class="block text-sm font-semibold">Reply-To (optioneel)<input type="email" name="reply_to_email" value="{{ old('reply_to_email', $documentSettings?->replyTo?->value()) }}" class="mt-2 w-full rounded-lg border-slate-300">@error('reply_to_email')<span class="text-red-700">{{ $message }}</span>@enderror</label>
            </div></section>
            @error('document_settings')<p class="text-red-700">{{ $message }}</p>@enderror
            <div class="flex justify-end"><button class="min-h-11 rounded-lg bg-blue-700 px-5 font-semibold text-white">Documentinstellingen opslaan</button></div>
        </form>

        @php($postingConfiguration = $salesPostingSettings->current->configuration())
        @php($postingStatus = match ($salesPostingSettings->current->status()) {
            \App\Application\Sales\SalesPostingConfigurationReadStatus::Missing => ['Niet ingesteld', 'bg-slate-100 text-slate-700'],
            \App\Application\Sales\SalesPostingConfigurationReadStatus::Success => ['Geldig', 'bg-green-100 text-green-800'],
            \App\Application\Sales\SalesPostingConfigurationReadStatus::InvalidReference => ['Ongeldig – aandacht vereist', 'bg-red-100 text-red-800'],
        })
        @php($hasPostingMasterData = $salesPostingSettings->salesJournals !== [] && $salesPostingSettings->accountsReceivableAccounts !== [] && $salesPostingSettings->revenueAccounts !== [] && $salesPostingSettings->outputVatAccounts !== [])

        <section aria-labelledby="sales-posting-heading" class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="sales-posting-heading" class="text-lg font-bold">Verkoopboekingen</h2>
                    <p class="mt-1 text-sm text-slate-600">Koppel expliciet het verkoopdagboek en de grootboekrekeningen voor nieuwe verkoopboekingen.</p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1 text-sm font-semibold {{ $postingStatus[1] }}" role="status">{{ $postingStatus[0] }}</span>
            </div>

            @if (! $hasPostingMasterData)
                <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">Er zijn nog geen geschikte dagboeken/grootboekrekeningen beschikbaar.</p>
            @endif

            @if ($postingConfiguration !== null)
                <dl class="mt-5 grid gap-3 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-semibold">Huidig verkoopdagboek</dt><dd class="mt-1 text-slate-700">{{ $salesPostingSettings->currentSalesJournal === null ? 'Niet beschikbaar' : $salesPostingSettings->currentSalesJournal->code()->value().' – '.$salesPostingSettings->currentSalesJournal->name()->value() }}</dd></div>
                    <div><dt class="font-semibold">Huidige debiteurenrekening</dt><dd class="mt-1 text-slate-700">{{ $salesPostingSettings->currentAccountsReceivableAccount === null ? 'Niet beschikbaar' : $salesPostingSettings->currentAccountsReceivableAccount->code()->value().' – '.$salesPostingSettings->currentAccountsReceivableAccount->name()->value() }}</dd></div>
                    <div><dt class="font-semibold">Huidige omzetrekening</dt><dd class="mt-1 text-slate-700">{{ $salesPostingSettings->currentRevenueAccount === null ? 'Niet beschikbaar' : $salesPostingSettings->currentRevenueAccount->code()->value().' – '.$salesPostingSettings->currentRevenueAccount->name()->value() }}</dd></div>
                    <div><dt class="font-semibold">Huidige btw-rekening</dt><dd class="mt-1 text-slate-700">{{ $salesPostingSettings->currentOutputVatAccount === null ? 'Niet beschikbaar' : $salesPostingSettings->currentOutputVatAccount->code()->value().' – '.$salesPostingSettings->currentOutputVatAccount->name()->value() }}</dd></div>
                </dl>
            @endif

            @error('sales_posting')<p id="sales-posting-error" class="mt-5 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

            <form method="POST" action="{{ route('settings.administration.sales-posting.update') }}" class="mt-5 grid gap-5 sm:grid-cols-2" @error('sales_posting') aria-describedby="sales-posting-error" @enderror>
                @csrf
                @method('PUT')
                <div>
                    <label for="sales_journal_id" class="block text-sm font-semibold">Verkoopdagboek</label>
                    <select id="sales_journal_id" name="sales_journal_id" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                        <option value="">Selecteer een verkoopdagboek</option>
                        @foreach ($salesPostingSettings->salesJournals as $journal)
                            <option value="{{ $journal->id()->toString() }}" @selected(old('sales_journal_id', $postingConfiguration?->salesJournalId()->toString()) === $journal->id()->toString())>{{ $journal->code()->value() }} – {{ $journal->name()->value() }}</option>
                        @endforeach
                    </select>
                    @error('sales_journal_id')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="accounts_receivable_ledger_account_id" class="block text-sm font-semibold">Debiteurenrekening</label>
                    <select id="accounts_receivable_ledger_account_id" name="accounts_receivable_ledger_account_id" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                        <option value="">Selecteer een debiteurenrekening</option>
                        @foreach ($salesPostingSettings->accountsReceivableAccounts as $account)
                            <option value="{{ $account->id()->toString() }}" @selected(old('accounts_receivable_ledger_account_id', $postingConfiguration?->accountsReceivableLedgerAccountId()->toString()) === $account->id()->toString())>{{ $account->code()->value() }} – {{ $account->name()->value() }}</option>
                        @endforeach
                    </select>
                    @error('accounts_receivable_ledger_account_id')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="revenue_ledger_account_id" class="block text-sm font-semibold">Omzetrekening</label>
                    <select id="revenue_ledger_account_id" name="revenue_ledger_account_id" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                        <option value="">Selecteer een omzetrekening</option>
                        @foreach ($salesPostingSettings->revenueAccounts as $account)
                            <option value="{{ $account->id()->toString() }}" @selected(old('revenue_ledger_account_id', $postingConfiguration?->revenueLedgerAccountId()->toString()) === $account->id()->toString())>{{ $account->code()->value() }} – {{ $account->name()->value() }}</option>
                        @endforeach
                    </select>
                    @error('revenue_ledger_account_id')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="output_vat_ledger_account_id" class="block text-sm font-semibold">Af te dragen btw-rekening</label>
                    <select id="output_vat_ledger_account_id" name="output_vat_ledger_account_id" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-blue-700 focus:ring-2 focus:ring-blue-700">
                        <option value="">Selecteer een btw-rekening</option>
                        @foreach ($salesPostingSettings->outputVatAccounts as $account)
                            <option value="{{ $account->id()->toString() }}" @selected(old('output_vat_ledger_account_id', $postingConfiguration?->outputVatLedgerAccountId()->toString()) === $account->id()->toString())>{{ $account->code()->value() }} – {{ $account->name()->value() }}</option>
                        @endforeach
                    </select>
                    @error('output_vat_ledger_account_id')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end sm:col-span-2">
                    <button type="submit" @disabled(! $hasPostingMasterData) class="min-h-11 rounded-lg bg-blue-700 px-5 py-2.5 font-semibold text-white hover:bg-blue-800 focus:ring-2 focus:ring-blue-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-400">Verkoopboekingen opslaan</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
