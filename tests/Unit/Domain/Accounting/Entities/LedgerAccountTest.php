<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\Entities;

use App\Domain\Accounting\Entities\LedgerAccount;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Enums\LedgerAccountType;
use App\Domain\Accounting\ValueObjects\LedgerAccountCode;
use App\Domain\Accounting\ValueObjects\LedgerAccountId;
use App\Domain\Accounting\ValueObjects\LedgerAccountName;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class LedgerAccountTest extends TestCase
{
    public function test_it_is_constructed_with_all_values_exposed(): void
    {
        $id = new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440000'));
        $code = new LedgerAccountCode('bank1000');
        $name = new LedgerAccountName('Bank account');
        $account = new LedgerAccount($id, $code, $name, LedgerAccountType::Asset, LedgerAccountStatus::Active);

        self::assertSame($id, $account->id());
        self::assertSame($code, $account->code());
        self::assertSame($name, $account->name());
        self::assertSame(LedgerAccountType::Asset, $account->type());
        self::assertSame(LedgerAccountStatus::Active, $account->status());
        self::assertTrue($account->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity_code_or_type(): void
    {
        $account = $this->createAccount();
        $id = $account->id();
        $code = $account->code();
        $type = $account->type();

        $account->rename(new LedgerAccountName('Primary bank account'));

        self::assertSame('Primary bank account', $account->name()->value());
        self::assertSame($id, $account->id());
        self::assertSame($code, $account->code());
        self::assertSame($type, $account->type());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $account = $this->createAccount();

        $account->deactivate();
        $account->deactivate();
        self::assertSame(LedgerAccountStatus::Inactive, $account->status());
        self::assertFalse($account->isActive());

        $account->activate();
        $account->activate();
        self::assertSame(LedgerAccountStatus::Active, $account->status());
        self::assertTrue($account->isActive());
    }

    public function test_it_has_no_code_type_or_balance_mutation_api(): void
    {
        self::assertFalse(method_exists(LedgerAccount::class, 'changeCode'));
        self::assertFalse(method_exists(LedgerAccount::class, 'changeType'));
        self::assertFalse(method_exists(LedgerAccount::class, 'balance'));
        self::assertFalse(method_exists(LedgerAccount::class, 'setBalance'));
    }

    private function createAccount(): LedgerAccount
    {
        return new LedgerAccount(
            new LedgerAccountId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new LedgerAccountCode('bank1000'),
            new LedgerAccountName('Bank account'),
            LedgerAccountType::Asset,
            LedgerAccountStatus::Active,
        );
    }
}
