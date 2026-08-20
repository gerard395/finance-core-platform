<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Application\Shared\TransactionManager;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\ValueObjects\DisplayName;
use App\Domain\Identity\ValueObjects\EmailAddress;
use App\Domain\Identity\ValueObjects\UserId;
use InvalidArgumentException;
use SensitiveParameter;

final readonly class ProvisionUserAccount
{
    public function __construct(
        private UserRepository $users,
        private AuthAccountStore $authAccounts,
        private PasswordHasher $passwordHasher,
        private TransactionManager $transactions,
    ) {}

    public function execute(
        UserId $userId,
        DisplayName $displayName,
        EmailAddress $emailAddress,
        #[SensitiveParameter] string $initialPassword,
    ): ProvisionUserAccountResult {
        if ($initialPassword === '') {
            throw new InvalidArgumentException('Initial password cannot be empty.');
        }

        return $this->transactions->run(function () use (
            $userId,
            $displayName,
            $emailAddress,
            $initialPassword,
        ): ProvisionUserAccountResult {
            if ($this->users->findById($userId) !== null) {
                throw UserAccountAlreadyExists::forDomainUserId();
            }

            if ($this->authAccounts->existsByEmail($emailAddress)) {
                throw UserAccountAlreadyExists::forEmail();
            }

            $user = new User($userId, $displayName, $emailAddress, UserStatus::Active);
            $this->users->save($user);

            $authAccount = $this->authAccounts->create(
                $userId,
                $displayName,
                $emailAddress,
                $this->passwordHasher->hash($initialPassword),
            );

            return new ProvisionUserAccountResult($userId, $authAccount->id());
        });
    }
}
