<?php

declare(strict_types=1);

namespace App\Domain\Identity\Definitions;

use App\Domain\Identity\ValueObjects\RoleCode;
use App\Domain\Identity\ValueObjects\RoleId;
use App\Domain\Identity\ValueObjects\RoleName;
use App\Domain\Shared\Identity\Uuid;

enum BankingRole: string
{
    case Manager = 'BANKING_MANAGER';
    case Poster = 'BANKING_POSTER';
    case ReversalOperator = 'BANKING_REVERSAL_OPERATOR';

    public function id(): RoleId
    {
        return new RoleId(new Uuid(match ($this) {
            self::Manager => 'b2020000-0000-4000-8000-000000000001',
            self::Poster => 'b2020000-0000-4000-8000-000000000002',
            self::ReversalOperator => 'b2020000-0000-4000-8000-000000000003',
        }));
    }

    public function code(): RoleCode
    {
        return new RoleCode($this->value);
    }

    public function name(): RoleName
    {
        return new RoleName(match ($this) {
            self::Manager => 'Banking Manager',
            self::Poster => 'Banking Poster',
            self::ReversalOperator => 'Banking Reversal Operator',
        });
    }

    /** @return list<BankingPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Manager => [BankingPermission::View, BankingPermission::ManagePayments],
            self::Poster => [BankingPermission::View, BankingPermission::PostPayments],
            self::ReversalOperator => [BankingPermission::View, BankingPermission::ReversePayments],
        };
    }
}
