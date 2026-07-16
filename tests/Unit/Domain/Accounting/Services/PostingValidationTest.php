<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Services;

use App\Domain\Accounting\Entities\JournalEntryLine;
use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\Services\PostingValidation;
use App\Domain\Accounting\ValueObjects\JournalEntryLineId;
use App\Domain\Accounting\ValueObjects\JournalEntryReference;
use App\Domain\Accounting\ValueObjects\JournalId;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Accounting\ValueObjects\ValidationResult;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PostingValidationTest extends TestCase
{
    public function test_an_empty_request_is_invalid(): void
    {
        $result = $this->validate([]);

        self::assertFalse($result->isValid());
        self::assertSame(['minimum_lines'], $this->errorCodes($result));
    }

    public function test_a_request_with_one_line_is_invalid(): void
    {
        $result = $this->validate([$this->debitLine('100')]);

        self::assertFalse($result->isValid());
        self::assertSame(['minimum_lines', 'unbalanced_entry'], $this->errorCodes($result));
    }

    public function test_two_balanced_lines_are_valid(): void
    {
        $result = $this->validate([
            $this->debitLine('100.12345678'),
            $this->creditLine('100.12345678'),
        ]);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());
    }

    public function test_multiple_lines_are_summed_without_floats(): void
    {
        $result = $this->validate([
            $this->debitLine('0.1'),
            $this->debitLine('0.2', '936da01f-9abd-4d9d-80c7-02af85c822a8'),
            $this->creditLine('0.3'),
        ]);

        self::assertTrue($result->isValid());
    }

    public function test_unequal_debit_and_credit_are_invalid(): void
    {
        $result = $this->validate([
            $this->debitLine('100'),
            $this->creditLine('99.99'),
        ]);

        self::assertContains('unbalanced_entry', $this->errorCodes($result));
    }

    public function test_different_currencies_are_invalid(): void
    {
        $result = $this->validate([
            $this->debitLine('100', currency: 'EUR'),
            $this->creditLine('100', currency: 'USD'),
        ]);

        self::assertContains('currency_mismatch', $this->errorCodes($result));
    }

    public function test_duplicate_line_identity_is_invalid(): void
    {
        $duplicateId = '123e4567-e89b-42d3-a456-426614174000';
        $result = $this->validate([
            $this->debitLine('100', $duplicateId),
            $this->creditLine('100', $duplicateId),
        ]);

        self::assertContains('duplicate_line_id', $this->errorCodes($result));
    }

    /** @param list<JournalEntryLine> $lines */
    private function validate(array $lines): ValidationResult
    {
        $request = new PostingRequest(
            new JournalId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new PostingDate(new DateTimeImmutable('2026-07-16')),
            new JournalEntryReference('Validation test'),
            $lines,
        );

        return (new PostingValidation)->validate($request);
    }

    private function debitLine(
        string $amount,
        string $id = '123e4567-e89b-42d3-a456-426614174000',
        string $currency = 'EUR',
    ): JournalEntryLine {
        return $this->line($id, new Money($amount, new Currency($currency)), null);
    }

    private function creditLine(
        string $amount,
        string $id = '6ba7b811-9dad-41d1-80b4-00c04fd430c8',
        string $currency = 'EUR',
    ): JournalEntryLine {
        return $this->line($id, null, new Money($amount, new Currency($currency)));
    }

    private function line(string $id, ?Money $debit, ?Money $credit): JournalEntryLine
    {
        return new JournalEntryLine(
            new JournalEntryLineId(new Uuid($id)),
            new LedgerAccountId(new Uuid('6ba7b810-9dad-41d1-80b4-00c04fd430c8')),
            $debit,
            $credit,
            'Validation line',
        );
    }

    /** @return list<string> */
    private function errorCodes(ValidationResult $result): array
    {
        return array_map(
            static fn ($error): string => $error->code(),
            $result->errors(),
        );
    }
}
