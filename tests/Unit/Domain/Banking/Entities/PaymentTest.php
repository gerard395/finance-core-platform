<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\Entities;

use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Banking\Entities\Payment;
use App\Domain\Banking\ValueObjects\PaymentId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function test_it_exposes_immutable_identity_open_item_and_amount(): void
    {
        $id = new PaymentId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $openItemId = new OpenItemId(new Uuid('550e8400-e29b-41d4-a716-446655440001'));
        $amount = new Money('25.50', new Currency('EUR'));
        $payment = new Payment($id, $openItemId, $amount);

        self::assertSame($id, $payment->id());
        self::assertSame($openItemId, $payment->openItemId());
        self::assertSame($amount, $payment->amount());
    }

    #[DataProvider('nonPositiveAmounts')]
    public function test_non_positive_amount_is_rejected(string $amount): void
    {
        $this->expectException(DomainException::class);

        $this->createPayment($amount);
    }

    /** @return array<string, array{string}> */
    public static function nonPositiveAmounts(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-0.01'],
        ];
    }

    public function test_it_has_no_posting_or_settlement_api(): void
    {
        $payment = $this->createPayment('10');

        self::assertFalse(method_exists($payment, 'journalEntry'));
        self::assertFalse(method_exists($payment, 'postingRequest'));
        self::assertFalse(method_exists($payment, 'settle'));
        self::assertFalse(method_exists($payment, 'match'));
    }

    private function createPayment(string $amount): Payment
    {
        return new Payment(
            new PaymentId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new OpenItemId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new Money($amount, new Currency('EUR')),
        );
    }
}
