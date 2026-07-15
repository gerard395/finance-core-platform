<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\BankAccount;
use App\Domain\Relations\Enums\BankAccountStatus;
use App\Domain\Relations\ValueObjects\AccountName;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class BankAccountTest extends TestCase
{
    public function test_constructor_rename_and_status_behavior(): void
    {
        $account = $this->createAccount();
        $id = $account->id();
        $iban = $account->iban();

        self::assertSame('NL91ABNA0417164300', $iban->value());
        self::assertSame('ABNANL2A', $account->bic()?->value());
        self::assertTrue($account->isActive());

        $account->rename(new AccountName('Operating Account'));
        self::assertSame('Operating Account', $account->accountName()->value());

        $account->deactivate();
        $account->deactivate();
        self::assertFalse($account->isActive());
        $account->activate();
        $account->activate();
        self::assertSame(BankAccountStatus::Active, $account->status());
        self::assertSame($id, $account->id());
        self::assertSame($iban, $account->iban());
    }

    private function createAccount(): BankAccount
    {
        return new BankAccount(
            new BankAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440030')),
            new Iban('nl91abna0417164300'),
            new Bic('abnanl2a'),
            new AccountName('Main Account'),
            BankAccountStatus::Active,
        );
    }
}
