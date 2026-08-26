<?php

declare(strict_types=1);

namespace App\Domain\Banking\Entities;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Banking\Enums\AdministrationBankAccountStatus;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankAccountLabel;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Shared\Finance\Currency;
use DomainException;

final class AdministrationBankAccount
{
    public function __construct(
        private readonly AdministrationBankAccountId $id,
        private readonly AdministrationId $administrationId,
        private readonly Iban $iban,
        private readonly ?Bic $bic,
        private AccountName $accountHolder,
        private BankAccountLabel $label,
        private readonly Currency $currency,
        private AdministrationBankAccountStatus $status,
    ) {
        if ($currency->code() !== 'EUR') {
            throw new DomainException('B2 operational bank accounts must use EUR.');
        }
    }

    public function id(): AdministrationBankAccountId
    {
        return $this->id;
    }

    public function administrationId(): AdministrationId
    {
        return $this->administrationId;
    }

    public function iban(): Iban
    {
        return $this->iban;
    }

    public function bic(): ?Bic
    {
        return $this->bic;
    }

    public function accountHolder(): AccountName
    {
        return $this->accountHolder;
    }

    public function label(): BankAccountLabel
    {
        return $this->label;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function status(): AdministrationBankAccountStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === AdministrationBankAccountStatus::Active;
    }

    public function rename(AccountName $holder, BankAccountLabel $label): void
    {
        $this->accountHolder = $holder;
        $this->label = $label;
    }

    public function activate(): void
    {
        $this->status = AdministrationBankAccountStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = AdministrationBankAccountStatus::Inactive;
    }
}
