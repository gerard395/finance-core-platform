<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Domain\Administration\Entities\Administration;
use App\Domain\Administration\ValueObjects\AdministrationCode;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Administration\ValueObjects\AdministrationName;
use App\Domain\Administration\ValueObjects\AdministrationStatus;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationMembershipRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentAdministrationRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationMembershipRecord;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentAdministrationMembershipRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_and_reconstitutes_a_membership(): void
    {
        [$user, $administration] = $this->persistParents();
        $membership = $this->membership($user->id(), $administration->id());
        $repository = new EloquentAdministrationMembershipRepository;

        $repository->save($membership);
        $reconstituted = $repository->findByUserAndAdministration($user->id(), $administration->id());

        self::assertInstanceOf(AdministrationMembership::class, $reconstituted);
        self::assertNotInstanceOf(AdministrationMembershipRecord::class, $reconstituted);
        self::assertTrue($membership->id()->equals($reconstituted->id()));
        self::assertTrue($user->id()->equals($reconstituted->userId()));
        self::assertTrue($administration->id()->equals($reconstituted->administrationId()));
        self::assertTrue($reconstituted->isActive());
        self::assertEquals($membership->validFrom(), $reconstituted->validFrom());
        self::assertEquals($membership->validUntil(), $reconstituted->validUntil());
    }

    public function test_it_updates_and_lists_memberships_without_duplication(): void
    {
        [$user, $administration] = $this->persistParents();
        $membership = $this->membership($user->id(), $administration->id());
        $repository = new EloquentAdministrationMembershipRepository;
        $repository->save($membership);

        $membership->deactivate();
        $repository->save($membership);

        self::assertCount(1, $repository->findForUser($user->id()));
        self::assertFalse($repository->findForUser($user->id())[0]->isActive());
        self::assertSame(1, AdministrationMembershipRecord::query()->count());
    }

    public function test_duplicate_user_administration_membership_is_rejected(): void
    {
        [$user, $administration] = $this->persistParents();
        $repository = new EloquentAdministrationMembershipRepository;
        $repository->save($this->membership($user->id(), $administration->id()));

        $this->expectException(QueryException::class);

        $repository->save(new AdministrationMembership(
            new AdministrationMembershipId(new Uuid('550e8400-e29b-41d4-a716-446655440099')),
            $user->id(),
            $administration->id(),
            true,
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
        ));
    }

    public function test_unknown_parent_identity_is_rejected_by_foreign_key(): void
    {
        [, $administration] = $this->persistParents();

        $this->expectException(QueryException::class);

        (new EloquentAdministrationMembershipRepository)->save($this->membership(
            new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440098')),
            $administration->id(),
        ));
    }

    /** @return array{User, Administration} */
    private function persistParents(): array
    {
        $user = new User(
            new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440001')),
            new DisplayName('Membership User'),
            new EmailAddress('membership@example.com'),
            UserStatus::Active,
        );
        $administration = new Administration(
            new AdministrationId(new Uuid('550e8400-e29b-41d4-a716-446655440002')),
            new AdministrationCode('MEMBER'),
            new AdministrationName('Membership Administration'),
            null,
            new Currency('EUR'),
            AdministrationStatus::Active,
        );
        (new EloquentUserRepository)->save($user);
        (new EloquentAdministrationRepository)->save($administration);

        return [$user, $administration];
    }

    private function membership(UserId $userId, AdministrationId $administrationId): AdministrationMembership
    {
        return new AdministrationMembership(
            new AdministrationMembershipId(new Uuid('550e8400-e29b-41d4-a716-446655440003')),
            $userId,
            $administrationId,
            true,
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
        );
    }
}
