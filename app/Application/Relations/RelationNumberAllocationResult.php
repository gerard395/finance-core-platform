<?php

declare(strict_types=1);

namespace App\Application\Relations;

use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\SupplierNumber;
use InvalidArgumentException;

final readonly class RelationNumberAllocationResult
{
    private function __construct(
        private RelationNumberAllocationStatus $status,
        private CustomerNumber|SupplierNumber|null $number,
    ) {
        if (($status === RelationNumberAllocationStatus::Success) !== ($number !== null)) {
            throw new InvalidArgumentException('A successful Relation number allocation requires exactly one number.');
        }
    }

    public static function success(CustomerNumber|SupplierNumber $number): self
    {
        return new self(RelationNumberAllocationStatus::Success, $number);
    }

    public static function sequenceMissing(): self
    {
        return new self(RelationNumberAllocationStatus::SequenceMissing, null);
    }

    public static function sequenceInactive(): self
    {
        return new self(RelationNumberAllocationStatus::SequenceInactive, null);
    }

    public function status(): RelationNumberAllocationStatus
    {
        return $this->status;
    }

    public function number(): CustomerNumber|SupplierNumber|null
    {
        return $this->number;
    }
}
