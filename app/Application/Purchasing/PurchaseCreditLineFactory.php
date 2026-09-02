<?php

declare(strict_types=1);

namespace App\Application\Purchasing;

use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Purchasing\Entities\PurchaseCreditInvoiceLine;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInternationalTaxSnapshot;

final readonly class PurchaseCreditLineFactory
{
    public function __construct(private PurchaseCreditIdentityGenerator $ids) {}

    /** @return list<PurchaseCreditInvoiceLine>|null */
    public function selected(PurchaseCreditSource $source, array $ids): ?array
    {
        if ($ids === []) {
            return [];
        } $wanted = [];
        foreach ($ids as $id) {
            $key = $id->toString();
            if (isset($wanted[$key])) {
                return null;
            }$wanted[$key] = true;
        }
        $lines = [];
        foreach ($source->invoice->lines() as $line) {
            $key = $line->id()->toString();
            if (! isset($wanted[$key])) {
                continue;
            }
            $international = null;
            if (($snapshot = $line->treatmentSnapshot()) !== null) {
                $postings = $source->taxPostingsByLine[$key] ?? [];
                $groups = [];
                $roles = [];
                foreach ($postings as $posting) {
                    $leg = $posting->legSnapshot();
                    if ($leg === null || ! $leg->definitionId->equals($snapshot->definitionId) || $leg->definitionVersion !== $snapshot->definitionVersion) {
                        return null;
                    }
                    $groups[$leg->groupId->toString()] = $leg->groupId;
                    $roles[$leg->role->value] = $posting;
                }
                $expected = array_values(array_filter($snapshot->legDefinitions, static fn ($leg) => $leg->role === TaxLegRole::VatPayable || ! $snapshot->deductibility->isZero()));
                $expectedRoles = array_map(static fn ($leg): string => $leg->role->value, $expected);
                $actualRoles = array_keys($roles);
                sort($expectedRoles);
                sort($actualRoles);
                if (count($groups) !== 1 || $actualRoles !== $expectedRoles) {
                    return null;
                }
                $first = reset($postings)->legSnapshot();
                $international = new PurchaseCreditInternationalTaxSnapshot(
                    $snapshot,
                    reset($groups),
                    array_map(static fn ($posting) => $posting->id(), $postings),
                    $line->gross(),
                    $first->assessedVat,
                    $first->deductibleVat,
                    $first->nonDeductibleTaxCost,
                );
            }
            $lines[] = new PurchaseCreditInvoiceLine($this->ids->lineId(), $line->description(), $line->quantity(), $line->unitPrice(), $line->id(), $line->account(), $line->tax(), $line->net(), $line->taxAmount(), $line->gross(), $source->taxPostingIds[$key] ?? null, $international);
            unset($wanted[$key]);
        }

        return $wanted === [] ? $lines : null;
    }
}
