<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesInvoicePosting;
use App\Application\Sales\SalesInvoicePostingAppendResult;
use App\Application\Sales\SalesInvoicePostingRepository;
use App\Domain\Accounting\ValueObjects\JournalEntryId;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\JournalEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Models\OpenItemRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoicePostingRecord;
use App\Infrastructure\Persistence\Eloquent\Models\SalesInvoiceRecord;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;

final class EloquentSalesInvoicePostingRepository implements SalesInvoicePostingRepository
{
    public function findForInvoice(AdministrationId $administrationId, SalesInvoiceId $salesInvoiceId): ?SalesInvoicePosting
    {
        $record = SalesInvoicePostingRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('sales_invoice_id', $salesInvoiceId->toString())
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function append(SalesInvoicePosting $posting): SalesInvoicePostingAppendResult
    {
        $administrationId = $posting->administrationId()->toString();

        if ($this->findForInvoice($posting->administrationId(), $posting->salesInvoiceId()) !== null) {
            return SalesInvoicePostingAppendResult::AlreadyExists;
        }

        foreach ([
            [SalesInvoiceRecord::class, $posting->salesInvoiceId()->toString()],
            [JournalEntryRecord::class, $posting->journalEntryId()->toString()],
            [OpenItemRecord::class, $posting->openItemId()->toString()],
        ] as [$recordClass, $id]) {
            if (! $recordClass::query()->where('administration_id', $administrationId)->whereKey($id)->exists()) {
                throw new DomainException('Sales invoice posting references must belong to the same Administration.');
            }
        }

        try {
            SalesInvoicePostingRecord::query()->create([
                'administration_id' => $administrationId,
                'sales_invoice_id' => $posting->salesInvoiceId()->toString(),
                'journal_entry_id' => $posting->journalEntryId()->toString(),
                'open_item_id' => $posting->openItemId()->toString(),
                'created_at' => $posting->createdAt(),
            ]);
        } catch (QueryException $exception) {
            if ($this->findForInvoice($posting->administrationId(), $posting->salesInvoiceId()) !== null) {
                return SalesInvoicePostingAppendResult::AlreadyExists;
            }

            throw $exception;
        }

        return SalesInvoicePostingAppendResult::Appended;
    }

    private function hydrate(SalesInvoicePostingRecord $record): SalesInvoicePosting
    {
        $createdAt = $record->getAttribute('created_at');

        return new SalesInvoicePosting(
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            new SalesInvoiceId(new Uuid($record->getAttribute('sales_invoice_id'))),
            new JournalEntryId(new Uuid($record->getAttribute('journal_entry_id'))),
            new OpenItemId(new Uuid($record->getAttribute('open_item_id'))),
            $createdAt instanceof DateTimeImmutable ? $createdAt : new DateTimeImmutable((string) $createdAt),
        );
    }
}
