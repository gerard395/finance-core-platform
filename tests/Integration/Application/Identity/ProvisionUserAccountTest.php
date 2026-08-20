<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Identity;

use App\Application\Identity\AuthAccount;
use App\Application\Identity\AuthAccountStore;
use App\Application\Identity\PasswordHasher;
use App\Application\Identity\ProvisionUserAccount;
use App\Application\Identity\UserAccountAlreadyExists;
use App\Application\Shared\TransactionManager as SharedTransactionManager;
use App\Domain\Identity\Entities\User as DomainUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Auth\EloquentAuthAccountStore;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Infrastructure\Persistence\Eloquent\Models\DomainUserRecord;
use App\Infrastructure\Persistence\LaravelDatabaseTransactionManager;
use App\Models\User as AuthUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class ProvisionUserAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_atomically_provisions_domain_user_and_auth_account(): void
    {
        $userId = $this->userId();
        $plainTextPassword = 'correct-horse-battery-staple';

        $result = $this->app->make(ProvisionUserAccount::class)->execute(
            $userId,
            new DisplayName('Provisioned User'),
            new EmailAddress('Provisioned@Example.com'),
            $plainTextPassword,
        );

        $domainUser = (new EloquentUserRepository)->findById($userId);
        $authUser = AuthUser::query()->sole();
        self::assertInstanceOf(DomainUser::class, $domainUser);
        self::assertSame(UserStatus::Active, $domainUser->status());
        self::assertSame('Provisioned User', $domainUser->displayName()->toString());
        self::assertSame('provisioned@example.com', $domainUser->emailAddress()->toString());
        self::assertSame($userId->toString(), $authUser->domain_user_id);
        self::assertSame('provisioned@example.com', $authUser->email);
        self::assertIsInt($authUser->id);
        self::assertTrue(Hash::check($plainTextPassword, $authUser->password));
        self::assertNotSame($plainTextPassword, $authUser->password);
        self::assertTrue($userId->equals($result->domainUserId()));
        self::assertSame($authUser->id, $result->authAccountId());
        self::assertFalse(Schema::hasColumn('domain_users', 'password'));
        self::assertFalse(Schema::hasColumn('domain_users', 'remember_token'));
    }

    public function test_duplicate_login_email_is_rejected_without_partial_domain_user(): void
    {
        $useCase = $this->app->make(ProvisionUserAccount::class);
        $useCase->execute(
            $this->userId(),
            new DisplayName('Existing User'),
            new EmailAddress('existing@example.com'),
            'first-secure-password',
        );

        $duplicateId = new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440002'));

        try {
            $useCase->execute(
                $duplicateId,
                new DisplayName('Duplicate User'),
                new EmailAddress('EXISTING@example.com'),
                'second-secure-password',
            );
            self::fail('Duplicate email should be rejected.');
        } catch (UserAccountAlreadyExists) {
            self::assertNull((new EloquentUserRepository)->findById($duplicateId));
            self::assertSame(1, DomainUserRecord::query()->count());
            self::assertSame(1, AuthUser::query()->count());
        }
    }

    public function test_empty_initial_password_is_rejected_before_persistence(): void
    {
        try {
            $this->app->make(ProvisionUserAccount::class)->execute(
                $this->userId(),
                new DisplayName('Passwordless User'),
                new EmailAddress('passwordless@example.com'),
                '',
            );
            self::fail('An empty initial password should be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, DomainUserRecord::query()->count());
            self::assertSame(0, AuthUser::query()->count());
        }
    }

    public function test_duplicate_domain_user_id_is_rejected_without_second_auth_account(): void
    {
        $useCase = $this->app->make(ProvisionUserAccount::class);
        $userId = $this->userId();
        $useCase->execute(
            $userId,
            new DisplayName('Existing User'),
            new EmailAddress('first@example.com'),
            'first-secure-password',
        );

        $this->expectException(UserAccountAlreadyExists::class);

        try {
            $useCase->execute(
                $userId,
                new DisplayName('Duplicate Identity'),
                new EmailAddress('second@example.com'),
                'second-secure-password',
            );
        } finally {
            self::assertSame(1, DomainUserRecord::query()->count());
            self::assertSame(1, AuthUser::query()->count());
        }
    }

    public function test_auth_creation_failure_rolls_back_domain_user(): void
    {
        $failingAuthAccounts = new class implements AuthAccountStore
        {
            public function existsByEmail(EmailAddress $emailAddress): bool
            {
                return false;
            }

            public function create(
                UserId $domainUserId,
                DisplayName $displayName,
                EmailAddress $emailAddress,
                string $passwordHash,
            ): AuthAccount {
                throw new RuntimeException('Simulated auth persistence failure.');
            }

            public function findByDomainUserId(UserId $domainUserId): ?AuthAccount
            {
                return null;
            }
        };
        $useCase = new ProvisionUserAccount(
            new EloquentUserRepository,
            $failingAuthAccounts,
            $this->app->make(PasswordHasher::class),
            $this->app->make(SharedTransactionManager::class),
        );

        try {
            $useCase->execute(
                $this->userId(),
                new DisplayName('Rollback User'),
                new EmailAddress('rollback@example.com'),
                'rollback-secure-password',
            );
            self::fail('Auth persistence failure should escape the transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated auth persistence failure.', $exception->getMessage());
            self::assertSame(0, DomainUserRecord::query()->count());
            self::assertSame(0, AuthUser::query()->count());
        }
    }

    public function test_database_rejects_duplicate_domain_user_bridge(): void
    {
        $userId = $this->userId();
        (new EloquentUserRepository)->save(new DomainUser(
            $userId,
            new DisplayName('Bridge User'),
            new EmailAddress('bridge-domain@example.com'),
            UserStatus::Active,
        ));
        $store = new EloquentAuthAccountStore;
        $hash = $this->app->make(PasswordHasher::class)->hash('bridge-secure-password');
        $store->create(
            $userId,
            new DisplayName('Bridge User'),
            new EmailAddress('bridge-one@example.com'),
            $hash,
        );

        $this->expectException(QueryException::class);

        $store->create(
            $userId,
            new DisplayName('Bridge User Two'),
            new EmailAddress('bridge-two@example.com'),
            $hash,
        );
    }

    public function test_auth_account_can_be_found_by_domain_user_id(): void
    {
        $userId = $this->userId();
        $result = $this->app->make(ProvisionUserAccount::class)->execute(
            $userId,
            new DisplayName('Mapped User'),
            new EmailAddress('mapped@example.com'),
            'mapped-secure-password',
        );

        $account = $this->app->make(AuthAccountStore::class)->findByDomainUserId($userId);

        self::assertInstanceOf(AuthAccount::class, $account);
        self::assertSame($result->authAccountId(), $account->id());
        self::assertTrue($userId->equals($account->domainUserId()));
        self::assertSame('mapped@example.com', $account->email());
    }

    public function test_ports_resolve_to_laravel_infrastructure_adapters(): void
    {
        self::assertInstanceOf(EloquentAuthAccountStore::class, $this->app->make(AuthAccountStore::class));
        self::assertInstanceOf(LaravelPasswordHasher::class, $this->app->make(PasswordHasher::class));
        self::assertInstanceOf(
            LaravelDatabaseTransactionManager::class,
            $this->app->make(SharedTransactionManager::class),
        );
    }

    private function userId(): UserId
    {
        return new UserId(new Uuid('550e8400-e29b-41d4-a716-446655440001'));
    }
}
