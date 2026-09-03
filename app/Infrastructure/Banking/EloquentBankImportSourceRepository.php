<?php

declare(strict_types=1);

namespace App\Infrastructure\Banking;

use App\Application\Banking\BankImportSourceRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Entities\BankImportBatch;
use App\Domain\Banking\Entities\BankStatement;
use App\Domain\Banking\Entities\BankStatementEntry;
use App\Domain\Banking\Enums\BankEntryDirection;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankImportBatchId;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankStatementId;
use App\Domain\Banking\ValueObjects\CamtNamespaceVersion;
use App\Domain\Banking\ValueObjects\CanonicalizationVersion;
use App\Domain\Banking\ValueObjects\OriginalFileHash;
use App\Domain\Banking\ValueObjects\SourceFormat;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentBankImportSourceRepository implements BankImportSourceRepository
{
    public function find(AdministrationId $administrationId, BankImportBatchId $id): ?BankImportBatch
    {
        $batch = DB::table('bank_import_batches')->where('administration_id', $administrationId->toString())->where('id', $id->toString())->first();
        if ($batch === null) {
            return null;
        }
        $statements = DB::table('bank_statements')->where('administration_id', $administrationId->toString())->where('bank_import_batch_id', $id->toString())->orderBy('source_ordinal')->get()->map(function ($statement) use ($administrationId): BankStatement {
            $entries = DB::table('bank_statement_entries')->where('administration_id', $administrationId->toString())->where('bank_statement_id', $statement->id)->orderBy('source_ordinal')->get()->map(fn ($entry): BankStatementEntry => new BankStatementEntry(new BankStatementEntryId(new Uuid($entry->id)), new DateTimeImmutable($entry->booking_date), $entry->value_date === null ? null : new DateTimeImmutable($entry->value_date), new Money($entry->signed_amount, new Currency($entry->currency)), BankEntryDirection::from($entry->direction), (bool) $entry->reversal, $entry->account_servicer_reference, $entry->entry_reference, $entry->end_to_end_id, $entry->counterparty_name, $entry->counterparty_account, json_decode($entry->remittance_lines, true, flags: JSON_THROW_ON_ERROR), $entry->creditor_reference, $entry->mandate_id, $entry->bank_transaction_domain, $entry->bank_transaction_family, $entry->bank_transaction_subfamily, $entry->bank_transaction_proprietary_code, json_decode($entry->normalized_metadata, true, flags: JSON_THROW_ON_ERROR), (int) $entry->source_ordinal))->all();

            return new BankStatement(new BankStatementId(new Uuid($statement->id)), $statement->external_id, $statement->electronic_sequence, $statement->account_identity, $statement->currency, $statement->opening_balance === null ? null : new Money($statement->opening_balance, new Currency($statement->currency)), $statement->closing_balance === null ? null : new Money($statement->closing_balance, new Currency($statement->currency)), $statement->period_from === null ? null : new DateTimeImmutable($statement->period_from), $statement->period_to === null ? null : new DateTimeImmutable($statement->period_to), $entries, (int) $statement->source_ordinal);
        })->all();

        return new BankImportBatch(new BankImportBatchId(new Uuid($batch->id)), new AdministrationId(new Uuid($batch->administration_id)), new AdministrationBankAccountId(new Uuid($batch->administration_bank_account_id)), SourceFormat::from($batch->source_format), CamtNamespaceVersion::from($batch->namespace_version), new OriginalFileHash($batch->original_file_hash), $batch->parser_version, new CanonicalizationVersion($batch->canonicalization_version), new UserId(new Uuid($batch->actor_id)), new DateTimeImmutable($batch->imported_at), $batch->artifact_reference, $statements);
    }

    public function insert(BankImportBatch $batch): bool
    {
        try {
            DB::transaction(function () use ($batch): void {
                DB::table('bank_import_batches')->insert(['id' => $batch->id->toString(), 'administration_id' => $batch->administrationId->toString(), 'administration_bank_account_id' => $batch->bankAccountId->toString(), 'source_format' => $batch->sourceFormat->value, 'namespace_version' => $batch->namespaceVersion->value, 'original_file_hash' => $batch->originalFileHash->value, 'parser_version' => $batch->parserVersion, 'canonicalization_version' => $batch->canonicalizationVersion->value, 'actor_id' => $batch->actorId->toString(), 'imported_at' => $batch->importedAt, 'artifact_reference' => $batch->artifactReference]);
                foreach ($batch->statements as $statement) {
                    DB::table('bank_statements')->insert(['id' => $statement->id->toString(), 'administration_id' => $batch->administrationId->toString(), 'bank_import_batch_id' => $batch->id->toString(), 'external_id' => $statement->externalId, 'electronic_sequence' => $statement->electronicSequence, 'account_identity' => $statement->accountIdentity, 'currency' => $statement->currency, 'opening_balance' => $statement->openingBalance?->amount(), 'closing_balance' => $statement->closingBalance?->amount(), 'period_from' => $statement->fromDate, 'period_to' => $statement->toDate, 'canonical_statement_hash' => $statement->canonicalStatementHash($batch->namespaceVersion->value, $batch->parserVersion, $batch->canonicalizationVersion), 'source_ordinal' => $statement->sourceOrdinal]);
                    foreach ($statement->entries as $entry) {
                        DB::table('bank_statement_entries')->insert(['id' => $entry->id->toString(), 'administration_id' => $batch->administrationId->toString(), 'bank_statement_id' => $statement->id->toString(), 'booking_date' => $entry->bookingDate, 'value_date' => $entry->valueDate, 'signed_amount' => $entry->amount->amount(), 'currency' => $entry->amount->currency()->code(), 'direction' => $entry->direction->value, 'reversal' => $entry->reversal, 'account_servicer_reference' => $entry->accountServicerReference, 'entry_reference' => $entry->entryReference, 'end_to_end_id' => $entry->endToEndId, 'counterparty_name' => $entry->counterpartyName, 'counterparty_account' => $entry->counterpartyAccount, 'remittance_lines' => json_encode($entry->remittanceLines, JSON_THROW_ON_ERROR), 'creditor_reference' => $entry->creditorReference, 'mandate_id' => $entry->mandateId, 'bank_transaction_domain' => $entry->bankTransactionDomain, 'bank_transaction_family' => $entry->bankTransactionFamily, 'bank_transaction_subfamily' => $entry->bankTransactionSubfamily, 'bank_transaction_proprietary_code' => $entry->bankTransactionProprietaryCode, 'normalized_metadata' => json_encode($entry->metadata, JSON_THROW_ON_ERROR), 'canonical_entry_hash' => $entry->canonicalEntryHash($statement->accountIdentity, $batch->namespaceVersion->value, $batch->parserVersion, $batch->canonicalizationVersion), 'source_ordinal' => $entry->sourceOrdinal]);
                    }
                }
            });

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
