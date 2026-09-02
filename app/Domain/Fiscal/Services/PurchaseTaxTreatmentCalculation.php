<?php

declare(strict_types=1);

namespace App\Domain\Fiscal\Services;

use App\Domain\Fiscal\Entities\TaxTreatmentDefinition;
use App\Domain\Fiscal\Enums\DeductibilityPolicy;
use App\Domain\Fiscal\Enums\SupplierVatMode;
use App\Domain\Fiscal\Enums\TaxLegRole;
use App\Domain\Fiscal\ValueObjects\DeductibilityBasisPoints;
use App\Domain\Fiscal\ValueObjects\MoneyTaxBreakdown;
use App\Domain\Fiscal\ValueObjects\PurchaseTaxCalculationResult;
use App\Domain\Fiscal\ValueObjects\TaxLeg;
use App\Domain\Fiscal\ValueObjects\TaxLegId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentGroupId;
use App\Domain\Fiscal\ValueObjects\TaxTreatmentSnapshot;
use App\Domain\Shared\Finance\Money;
use DomainException;

final class PurchaseTaxTreatmentCalculation
{
    /** @param array<string, TaxLegId> $legIdsByRole */
    public function calculate(
        TaxTreatmentDefinition $definition,
        TaxTreatmentGroupId $groupId,
        array $legIdsByRole,
        Money $supplierNet,
        Money $supplierTaxCharged,
        DeductibilityBasisPoints $deductibility,
    ): PurchaseTaxCalculationResult {
        $this->assertInput($definition, $supplierNet, $supplierTaxCharged, $deductibility);

        $currency = $supplierNet->currency();
        $supplierGross = $supplierNet->add($supplierTaxCharged);
        $selfAssessedVat = $definition->supplierVatMode() === SupplierVatMode::SelfAssessed
            ? $this->percentage($supplierNet, $definition->vatRate()->value())
            : Money::zero($currency);
        $assessedVat = $supplierTaxCharged->add($selfAssessedVat);
        $deductibleVat = $this->basisPoints($assessedVat, $deductibility->value());
        $nonDeductible = $assessedVat->subtract($deductibleVat);
        $amounts = new MoneyTaxBreakdown(
            $supplierNet,
            $supplierTaxCharged,
            $supplierGross,
            $selfAssessedVat,
            $deductibleVat,
            $nonDeductible,
        );

        $legs = [];
        $primaryClassification = null;
        foreach ($definition->legDefinitions() as $legDefinition) {
            $amount = $legDefinition->role === TaxLegRole::VatPayable ? $assessedVat : $deductibleVat;
            $primaryClassification ??= $legDefinition->reportingClassification;
            if ($amount->isZero() && ! $legDefinition->emitWhenZero) {
                continue;
            }
            $id = $legIdsByRole[$legDefinition->role->value] ?? null;
            if (! $id instanceof TaxLegId) {
                throw new DomainException('Every realized tax leg requires an explicit identity.');
            }
            $legs[] = new TaxLeg(
                $id,
                $groupId,
                $legDefinition->role,
                $legDefinition->direction,
                $supplierNet,
                $definition->vatRate(),
                $amount,
                $legDefinition->reportingClassification,
                $legDefinition->ledgerAccountRole,
            );
        }

        if ($primaryClassification === null) {
            throw new DomainException('A tax treatment definition requires reporting truth.');
        }

        return new PurchaseTaxCalculationResult(
            $amounts,
            new TaxTreatmentSnapshot(
                $definition->id(),
                $definition->version(),
                $definition->treatmentType(),
                $definition->jurisdiction(),
                $definition->supplierVatMode(),
                $primaryClassification,
                $deductibility,
                $amounts,
            ),
            $legs,
        );
    }

    private function assertInput(TaxTreatmentDefinition $definition, Money $supplierNet, Money $supplierTaxCharged, DeductibilityBasisPoints $deductibility): void
    {
        if ($supplierNet->isNegative() || $supplierTaxCharged->isNegative() || ! $supplierNet->currency()->equals($supplierTaxCharged->currency())) {
            throw new DomainException('Supplier tax amounts must be non-negative and use one currency.');
        }
        if ($supplierNet->currency()->code() !== 'EUR') {
            throw new DomainException('International purchase VAT V1 supports EUR only.');
        }
        if ($definition->supplierVatMode() === SupplierVatMode::SelfAssessed && ! $supplierTaxCharged->isZero()) {
            throw new DomainException('Self-assessed treatments cannot contain supplier-charged VAT.');
        }

        $valid = match ($definition->deductibilityPolicy()) {
            DeductibilityPolicy::NotApplicable, DeductibilityPolicy::FixedZero => $deductibility->value() === 0,
            DeductibilityPolicy::FixedFull => $deductibility->value() === 10000,
            DeductibilityPolicy::UserSpecifiedLineRate => true,
        };
        if (! $valid) {
            throw new DomainException('Deductibility does not satisfy the treatment policy.');
        }
    }

    private function percentage(Money $amount, string $percentage): Money
    {
        [$amountDigits, $amountScale] = $this->digitsAndScale($amount->amount());
        [$rateDigits, $rateScale] = $this->digitsAndScale($percentage);

        return new Money(
            $this->roundScaled($this->multiplyDigits($amountDigits, $rateDigits), $amountScale + $rateScale + 2, 8),
            $amount->currency(),
        );
    }

    private function basisPoints(Money $amount, int $basisPoints): Money
    {
        [$digits, $scale] = $this->digitsAndScale($amount->amount());

        return new Money(
            $this->roundScaled($this->multiplyDigits($digits, (string) $basisPoints), $scale + 4, 8),
            $amount->currency(),
        );
    }

    /** @return array{string, int} */
    private function digitsAndScale(string $value): array
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return [ltrim($whole.$fraction, '0') ?: '0', strlen($fraction)];
    }

    private function multiplyDigits(string $left, string $right): string
    {
        $result = array_fill(0, strlen($left) + strlen($right), 0);
        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            for ($j = strlen($right) - 1; $j >= 0; $j--) {
                $position = $i + $j + 1;
                $value = ((int) $left[$i] * (int) $right[$j]) + $result[$position];
                $result[$position] = $value % 10;
                $result[$position - 1] += intdiv($value, 10);
            }
        }

        return ltrim(implode('', $result), '0') ?: '0';
    }

    private function roundScaled(string $digits, int $scale, int $targetScale): string
    {
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        if ($scale > $targetScale) {
            $cut = strlen($digits) - ($scale - $targetScale);
            $kept = substr($digits, 0, $cut);
            if ((int) $digits[$cut] >= 5) {
                $kept = $this->incrementDigits($kept);
            }
            $digits = $kept;
            $scale = $targetScale;
        }
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = $scale === 0 ? $digits : substr($digits, 0, -$scale);
        $fraction = $scale === 0 ? '' : rtrim(substr($digits, -$scale), '0');

        return (ltrim($whole, '0') ?: '0').($fraction === '' ? '' : '.'.$fraction);
    }

    private function incrementDigits(string $digits): string
    {
        $carry = 1;
        $result = '';
        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $sum = (int) $digits[$index] + $carry;
            $result = ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return ($carry > 0 ? '1' : '').$result;
    }
}
