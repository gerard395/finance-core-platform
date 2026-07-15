<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Identity\Entities;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $user = $this->createUser();

        self::assertSame('Finance User', $user->displayName()->value());
        self::assertSame('user@example.com', $user->emailAddress()->value());
        self::assertSame(UserStatus::Active, $user->status());
        self::assertTrue($user->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity(): void
    {
        $user = $this->createUser();
        $id = $user->id();

        $user->rename(new DisplayName('Renamed User'));

        self::assertSame('Renamed User', $user->displayName()->value());
        self::assertSame($id, $user->id());
    }

    public function test_its_email_address_can_be_changed(): void
    {
        $user = $this->createUser();

        $user->changeEmailAddress(new EmailAddress('Changed@Example.com'));

        self::assertSame('changed@example.com', $user->emailAddress()->value());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $user = $this->createUser();

        $user->deactivate();
        $user->deactivate();
        self::assertSame(UserStatus::Inactive, $user->status());
        self::assertFalse($user->isActive());

        $user->activate();
        $user->activate();
        self::assertSame(UserStatus::Active, $user->status());
        self::assertTrue($user->isActive());
    }

    private function createUser(): User
    {
        return new User(
            new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new DisplayName('Finance User'),
            new EmailAddress('User@Example.com'),
            UserStatus::Active,
        );
    }
}
