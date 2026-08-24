<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesCreditInvoicePosting;
use App\Application\Sales\SalesCreditInvoicePostingAppendResult;
use App\Application\Sales\SalesCreditInvoicePostingRepository;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoicePostingRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesCreditInvoiceRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentSalesCreditInvoicePostingRepository implements SalesCreditInvoicePostingRepository
{
    public function findForCreditInvoice(AdministrationId $administrationId, SalesCreditInvoiceId $creditInvoiceId): ?SalesCreditInvoicePosting
    {
        $record = SalesCreditInvoicePostingRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('sales_credit_invoice_id', $creditInvoiceId->toString())
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function append(SalesCreditInvoicePosting $posting): SalesCreditInvoicePostingAppendResult
    {
        $administrationId = $posting->administrationId()->toString();
        if ($this->findForCreditInvoice($posting->administrationId(), $posting->salesCreditInvoiceId()) !== null) {
            return SalesCreditInvoicePostingAppendResult::AlreadyExists;
        }
        foreach ([
            [SalesCreditInvoiceRecord::class, $posting->salesCreditInvoiceId()->toString()],
            [JournalEntryRecord::class, $posting->journalEntryId()->toString()],
            [OpenItemRecord::class, $posting->openItemId()->toString()],
        ] as [$recordClass, $id]) {
            if (! $recordClass::query()->where('administration_id', $administrationId)->whereKey($id)->exists()) {
                throw new DomainException('Sales credit invoice posting references must belong to the same Administration.');
            }
        }
        try {
            SalesCreditInvoicePostingRecord::query()->create([
                'administration_id' => $administrationId,
                'sales_credit_invoice_id' => $posting->salesCreditInvoiceId()->toString(),
                'journal_entry_id' => $posting->journalEntryId()->toString(),
                'open_item_id' => $posting->openItemId()->toString(),
                'created_at' => $posting->createdAt(),
            ]);
        } catch (QueryException $exception) {
            if ($this->findForCreditInvoice($posting->administrationId(), $posting->salesCreditInvoiceId()) !== null) {
                return SalesCreditInvoicePostingAppendResult::AlreadyExists;
            }
            throw $exception;
        }

        return SalesCreditInvoicePostingAppendResult::Appended;
    }

    private function hydrate(SalesCreditInvoicePostingRecord $record): SalesCreditInvoicePosting
    {
        $createdAt = $record->getAttribute('created_at');

        return new SalesCreditInvoicePosting(
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new SalesCreditInvoiceId(new Uuid($record->getAttribute('sales_credit_invoice_id'))),
            new JournalEntryId(new Uuid($record->getAttribute('journal_entry_id'))),
            new OpenItemId(new Uuid($record->getAttribute('open_item_id'))),
            $createdAt instanceof DateTimeImmutable ? $createdAt : new DateTimeImmutable((string) $createdAt),
        );
    }
}
