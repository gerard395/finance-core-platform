<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Services;

use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxPostingType;
use DomainException;

final readonly class TaxPostingReversalPolicy
{
    /** @param list<TaxPosting> $history */
    public function assertCanReverse(
        TaxPosting $original,
        TaxPosting $reversal,
        array $history,
    ): void {
        if ($original->type() !== TaxPostingType::Original) {
            throw new DomainException('A reversal can only target an original tax posting.');
        }

        if ($reversal->type() !== TaxPostingType::Reversal) {
            throw new DomainException('The candidate tax posting must be a reversal.');
        }

        $targetId = $reversal->reversedTaxPostingId();

        if ($targetId === null || ! $targetId->equals($original->id())) {
            throw new DomainException('The reversal must reference the supplied original tax posting.');
        }

        $target = null;

        foreach ($history as $posting) {
            if ($posting->id()->equals($targetId)) {
                $target = $posting;
            }

            if ($posting->type() === TaxPostingType::Reversal
                && $posting->reversedTaxPostingId()?->equals($targetId)) {
                throw new DomainException('An original tax posting can be reversed only once.');
            }
        }

        if ($target === null) {
            throw new DomainException('The original tax posting does not exist in the supplied history.');
        }

        if ($target->type() !== TaxPostingType::Original) {
            throw new DomainException('A reversal cannot target another reversal.');
        }

        if (! $target->id()->equals($original->id())) {
            throw new DomainException('The supplied original does not match the history target.');
        }

        if ($reversal->direction() !== $original->direction()) {
            throw new DomainException('A reversal must retain the original tax posting direction.');
        }

        if (! $reversal->taxCodeId()->equals($original->taxCodeId())) {
            throw new DomainException('A reversal must retain the original tax code.');
        }

        if (! $reversal->taxRate()->equals($original->taxRate())) {
            throw new DomainException('A reversal must retain the original tax rate snapshot.');
        }

        if (! $reversal->taxableBase()->currency()->equals($original->taxableBase()->currency())
            || ! $reversal->taxAmount()->currency()->equals($original->taxAmount()->currency())) {
            throw new DomainException('A reversal must retain the original currency.');
        }

        if (! $reversal->taxableBase()->equals($original->taxableBase())) {
            throw new DomainException('A reversal must retain the original taxable base.');
        }

        if (! $reversal->taxAmount()->equals($original->taxAmount())) {
            throw new DomainException('A reversal must retain the original tax amount.');
        }

        if (! $reversal->administrationId()->equals($original->administrationId())) {
            throw new DomainException('A reversal must retain the original administration.');
        }
    }
}
