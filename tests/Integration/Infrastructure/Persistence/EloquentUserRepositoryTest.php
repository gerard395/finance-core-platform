<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Application\Identity\UserRepository;
use App\Domain\Identity\Entities\User as DomainUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Infrastructure\Persistence\Eloquent\Models\DomainUserRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_and_reconstitutes_a_domain_user(): void
    {
        $repository = new EloquentUserRepository;
        $user = $this->user();

        $repository->save($user);

        $reconstituted = $repository->findById($user->id());

        self::assertInstanceOf(DomainUser::class, $reconstituted);
        self::assertNotInstanceOf(DomainUserRecord::class, $reconstituted);
        self::assertTrue($user->id()->equals($reconstituted->id()));
        self::assertSame('Finance User', $reconstituted->displayName()->toString());
        self::assertSame('finance@example.com', $reconstituted->emailAddress()->toString());
        self::assertSame(UserStatus::Active, $reconstituted->status());
    }

    public function test_save_updates_an_existing_user_without_duplication(): void
    {
        $repository = new EloquentUserRepository;
        $user = $this->user();
        $repository->save($user);

        $user->rename(new DisplayName('Updated User'));
        $user->deactivate();
        $repository->save($user);

        self::assertSame(1, DomainUserRecord::query()->count());
        self::assertSame('Updated User', $repository->findById($user->id())?->displayName()->toString());
        self::assertSame(UserStatus::Inactive, $repository->findById($user->id())?->status());
    }

    public function test_find_by_id_returns_null_for_an_unknown_user(): void
    {
        $repository = new EloquentUserRepository;

        self::assertNull($repository->findById($this->user()->id()));
    }

    public function test_application_contract_resolves_to_the_eloquent_adapter(): void
    {
        self::assertInstanceOf(EloquentUserRepository::class, $this->app->make(UserRepository::class));
    }

    private function user(): DomainUser
    {
        return new DomainUser(
            new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new DisplayName('Finance User'),
            new EmailAddress('Finance@Example.com'),
            UserStatus::Active,
        );
    }
}
