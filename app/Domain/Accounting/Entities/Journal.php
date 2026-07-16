<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Entities;

use App\Domain\Accounting\Enums\JournalStatus;
use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Accounting\ValueObjects\JournalCode;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\JournalName;

final class Journal
{
    public function __construct(
        private readonly JournalId $id,
        private readonly JournalCode $code,
        private JournalName $name,
        private readonly JournalType $type,
        private JournalStatus $status,
    ) {}

    public function id(): JournalId
    {
        return $this->id;
    }

    public function code(): JournalCode
    {
        return $this->code;
    }

    public function name(): JournalName
    {
        return $this->name;
    }

    public function type(): JournalType
    {
        return $this->type;
    }

    public function status(): JournalStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === JournalStatus::Active;
    }

    public function rename(JournalName $name): void
    {
        $this->name = $name;
    }

    public function activate(): void
    {
        $this->status = JournalStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = JournalStatus::Inactive;
    }
}
