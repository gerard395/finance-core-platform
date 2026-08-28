<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Accounting\ValueObjects\OpenItemSettlementId;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalId;
use App\Domain\Banking\ValueObjects\BankTransactionSettlementReversalLinkId;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;

final readonly class BankTransactionSettlementReversalLink
{
    public function __construct(
        public BankTransactionSettlementReversalLinkId $id,
        public AdministrationId $administrationId,
        public BankTransactionReversalId $bankTransactionReversalId,
        public PaymentAllocationId $paymentAllocationId,
        public OpenItemId $openItemId,
        public OpenItemSettlementId $originalOpenItemSettlementId,
        public OpenItemSettlementId $reversalOpenItemSettlementId,
    ) {}
}
