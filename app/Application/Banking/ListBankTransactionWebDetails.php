<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Administration\ValueObjects\AdministrationId;

final readonly class ListBankTransactionWebDetails
{
    public function __construct(private BankTransactionRepository $transactions, private GetBankTransactionWebDetail $details) {}

    /** @return list<BankTransactionWebDetail> */
    public function execute(AdministrationId $administrationId): array
    {
        $result = [];
        foreach ($this->transactions->list($administrationId) as $transaction) {
            $detail = $this->details->execute($administrationId, $transaction->id());
            if ($detail !== null) {
                $result[] = $detail;
            }
        }

        return $result;
    }
}
