<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;

final class LedgerAccount
{
    public function __construct(
        private readonly LedgerAccountId $id,
        private readonly LedgerAccountCode $code,
        private LedgerAccountName $name,
        private readonly LedgerAccountType $type,
        private LedgerAccountStatus $status,
    ) {}

    public function id(): LedgerAccountId
    {
        return $this->id;
    }

    public function code(): LedgerAccountCode
    {
        return $this->code;
    }

    public function name(): LedgerAccountName
    {
        return $this->name;
    }

    public function type(): LedgerAccountType
    {
        return $this->type;
    }

    public function status(): LedgerAccountStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === LedgerAccountStatus::Active;
    }

    public function rename(LedgerAccountName $name): void
    {
        $this->name = $name;
    }

    public function activate(): void
    {
        $this->status = LedgerAccountStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = LedgerAccountStatus::Inactive;
    }
}
