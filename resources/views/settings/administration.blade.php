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
