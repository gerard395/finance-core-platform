<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\TransactionManager;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Sales\Entities\Quotation;
use App\Domain\Sales\ValueObjects\QuotationId;
use Closure;
use DomainException;
use InvalidArgumentException;

final readonly class QuotationMutationService
{
    public function __construct(private QuotationReadRepository $reader, private QuotationUpdater $updater, private TransactionManager $transactions) {}

    /** @param Closure(Quotation): void $mutation */
    public function mutate(AdministrationId $administrationId, QuotationId $quotationId, Closure $mutation): QuotationWriteResult
    {
        return $this->transactions->run(function () use ($administrationId, $quotationId, $mutation): QuotationWriteResult {
            $quotation = $this->reader->findForAdministration($administrationId, $quotationId);
            if ($quotation === null) {
                return QuotationWriteResult::NotFound;
            }
            try {
                $mutation($quotation);
            } catch (DomainException|InvalidArgumentException) {
                return QuotationWriteResult::InvalidState;
            }

            return $this->updater->update($administrationId, $quotation);
        });
    }
}
