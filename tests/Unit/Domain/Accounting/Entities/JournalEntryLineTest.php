<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Entities;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JournalEntryLineTest extends TestCase
{
    public function test_it_is_constructed_with_a_debit_amount(): void
    {
        $id = $this->lineId();
        $ledgerAccountId = $this->ledgerAccountId();
        $debit = $this->money('100.50');
        $line = new JournalEntryLine($id, $ledgerAccountId, $debit, null, 'Bank receipt');

        self::assertSame($id, $line->id());
        self::assertSame($ledgerAccountId, $line->ledgerAccountId());
        self::assertSame($debit, $line->debit());
        self::assertNull($line->credit());
        self::assertSame('Bank receipt', $line->description());
    }

    public function test_it_is_constructed_with_a_credit_amount(): void
    {
        $credit = $this->money('100.50');
        $line = new JournalEntryLine($this->lineId(), $this->ledgerAccountId(), null, $credit, 'Revenue');

        self::assertNull($line->debit());
        self::assertSame($credit, $line->credit());
    }

    #[DataProvider('invalidAmountCombinations')]
    public function test_it_requires_exactly_one_non_negative_amount(?string $debit, ?string $credit): void
    {
        $this->expectException(DomainException::class);

        new JournalEntryLine(
            $this->lineId(),
            $this->ledgerAccountId(),
            $debit === null ? null : $this->money($debit),
            $credit === null ? null : $this->money($credit),
            'Invalid line',
        );
    }

    /** @return array<string, array{?string, ?string}> */
    public static function invalidAmountCombinations(): array
    {
        return [
            'both filled' => ['100', '100'],
            'both empty' => [null, null],
            'negative debit' => ['-0.01', null],
            'negative credit' => [null, '-0.01'],
        ];
    }

    private function lineId(): JournalEntryLineId
    {
        return new JournalEntryLineId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
    }

    private function ledgerAccountId(): LedgerAccountId
    {
        return new LedgerAccountId(new Uuid('123e4567-e89b-42d3-a456-426614174000'));
    }

    private function money(string $amount): Money
    {
        return new Money($amount, new Currency('EUR'));
    }
}
