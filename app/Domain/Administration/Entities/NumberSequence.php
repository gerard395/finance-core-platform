<?php

declare(strict_types=1);

namespace App\Domain\Administration\Entities;

use App\Domain\Administration\Enums\DocumentType;
use App\Domain\Administration\Enums\NumberSequenceResetPolicy;
use App\Domain\Administration\ValueObjects\NumberSequenceCode;
use App\Domain\Administration\ValueObjects\NumberSequenceId;
use App\Domain\Administration\ValueObjects\NumberSequenceName;
use App\Domain\Administration\ValueObjects\NumberSequencePrefix;
use App\Domain\Administration\ValueObjects\NumberSequenceSuffix;
use App\Domain\Administration\ValueObjects\PaddingLength;
use DomainException;
use InvalidArgumentException;

final class NumberSequence
{
    public function __construct(
        private readonly NumberSequenceId $id,
        private readonly NumberSequenceCode $code,
        private readonly NumberSequenceName $name,
        private readonly DocumentType $documentType,
        private readonly NumberSequencePrefix $prefix,
        private readonly NumberSequenceSuffix $suffix,
        private readonly PaddingLength $paddingLength,
        private int $nextNumber,
        private readonly NumberSequenceResetPolicy $resetPolicy,
        private bool $active,
    ) {
        if ($nextNumber < 1) {
            throw new InvalidArgumentException('Next number must be at least 1.');
        }
    }

    public function id(): NumberSequenceId
    {
        return $this->id;
    }

    public function code(): NumberSequenceCode
    {
        return $this->code;
    }

    public function name(): NumberSequenceName
    {
        return $this->name;
    }

    public function documentType(): DocumentType
    {
        return $this->documentType;
    }

    public function prefix(): NumberSequencePrefix
    {
        return $this->prefix;
    }

    public function suffix(): NumberSequenceSuffix
    {
        return $this->suffix;
    }

    public function paddingLength(): PaddingLength
    {
        return $this->paddingLength;
    }

    public function resetPolicy(): NumberSequenceResetPolicy
    {
        return $this->resetPolicy;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function nextNumber(): int
    {
        $number = $this->nextNumber;
        $this->nextNumber++;

        return $number;
    }

    public function peekNextNumber(): int
    {
        return $this->nextNumber;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function generateNumber(): string
    {
        if (! $this->active) {
            throw new DomainException('An inactive number sequence cannot generate numbers.');
        }

        $number = str_pad(
            (string) $this->nextNumber(),
            $this->paddingLength->value(),
            '0',
            STR_PAD_LEFT,
        );

        return $this->prefix->value().$number.$this->suffix->value();
    }
}
