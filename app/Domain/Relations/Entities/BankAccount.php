<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\Enums\BankAccountStatus;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;

final class BankAccount
{
    public function __construct(
        private readonly BankAccountId $id,
        private readonly Iban $iban,
        private readonly ?Bic $bic,
        private AccountName $accountName,
        private BankAccountStatus $status,
    ) {}

    public function id(): BankAccountId
    {
        return $this->id;
    }

    public function iban(): Iban
    {
        return $this->iban;
    }

    public function bic(): ?Bic
    {
        return $this->bic;
    }

    public function accountName(): AccountName
    {
        return $this->accountName;
    }

    public function status(): BankAccountStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === BankAccountStatus::Active;
    }

    public function rename(AccountName $accountName): void
    {
        $this->accountName = $accountName;
    }

    public function activate(): void
    {
        $this->status = BankAccountStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = BankAccountStatus::Inactive;
    }
}
