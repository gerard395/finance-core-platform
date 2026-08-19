<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Fiscal\Entities\TaxPosting;
use App\Domain\Fiscal\Enums\TaxPostingDirection;
use App\Domain\Fiscal\Enums\TaxPostingType;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use DateTimeImmutable;
use DomainException;

final readonly class VatOverview
{
    public function calculate(array $postings, AdministrationId $administrationId, Currency $currency, DateTimeImmutable $startDate, DateTimeImmutable $endDate): VatOverviewResult
    {
        if ($startDate > $endDate) {
            throw new DomainException('VAT overview start date cannot exceed end date.');
        }
        $selected = [];
        foreach ($postings as $posting) {
            $date = $posting->postingDate()->value();
            if (! $posting->administrationId()->equals($administrationId) || $date < $startDate || $date > $endDate) {
                continue;
            }
            if (! $posting->taxableBase()->currency()->equals($currency)) {
                throw new DomainException('A VAT overview must use one currency.');
            }
            $selected[] = $posting;
        }
        usort($selected, static fn (TaxPosting $a, TaxPosting $b): int => ($a->postingDate()->value() <=> $b->postingDate()->value()) ?: strcmp($a->id()->toString(), $b->id()->toString()));
        $zero = Money::zero($currency);
        $otb = $zero;
        $ot = $zero;
        $itb = $zero;
        $it = $zero;
        $groups = [];
        foreach ($selected as $p) {
            $add = $p->type() === TaxPostingType::Original;
            if ($p->direction() === TaxPostingDirection::Output) {
                $otb = $add ? $otb->add($p->taxableBase()) : $otb->subtract($p->taxableBase());
                $ot = $add ? $ot->add($p->taxAmount()) : $ot->subtract($p->taxAmount());
            } else {
                $itb = $add ? $itb->add($p->taxableBase()) : $itb->subtract($p->taxableBase());
                $it = $add ? $it->add($p->taxAmount()) : $it->subtract($p->taxAmount());
            }
            $key = $p->taxCodeId()->toString().'@'.$p->taxRate()->value();
            $g = $groups[$key] ?? [$p->taxCodeId(), $p->taxRate(), $zero, $zero, $zero, $zero];
            $i = $p->direction() === TaxPostingDirection::Output ? 2 : 4;
            $g[$i] = $add ? $g[$i]->add($p->taxableBase()) : $g[$i]->subtract($p->taxableBase());
            $g[$i + 1] = $add ? $g[$i + 1]->add($p->taxAmount()) : $g[$i + 1]->subtract($p->taxAmount());
            $groups[$key] = $g;
        }
        ksort($groups);
        $summaries = array_map(static fn (array $g) => new VatOverviewTaxCodeSummary(...$g), array_values($groups));

        return new VatOverviewResult($administrationId, $startDate, $endDate, $currency, array_map(static fn (TaxPosting $p) => new VatOverviewLine($p), $selected), $summaries, $otb, $ot, $itb, $it);
    }
}
