<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Identity\AdministrationMembershipRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Identity\Entities\AdministrationMembership;
use App\Domain\Identity\ValueObjects\AdministrationMembershipId;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationMembershipRecord;
use DateTimeImmutable;

final class EloquentAdministrationMembershipRepository implements AdministrationMembershipRepository
{
    public function findByUserAndAdministration(
        UserId $userId,
        AdministrationId $administrationId,
    ): ?AdministrationMembership {
        $record = AdministrationMembershipRecord::query()
            ->where('user_id', $userId->toString())
            ->where('administration_id', $administrationId->toString())
            ->first();

        return $record === null ? null : $this->reconstitute($record);
    }

    public function findForUser(UserId $userId): array
    {
        return AdministrationMembershipRecord::query()
            ->where('user_id', $userId->toString())
            ->orderBy('administration_id')
            ->get()
            ->map(fn (AdministrationMembershipRecord $record): AdministrationMembership => $this->reconstitute($record))
            ->all();
    }

    public function save(AdministrationMembership $membership): void
    {
        AdministrationMembershipRecord::query()->updateOrCreate(
            ['id' => $membership->id()->toString()],
            [
                'user_id' => $membership->userId()->toString(),
                'administration_id' => $membership->administrationId()->toString(),
                'active' => $membership->isActive(),
                'valid_from' => $membership->validFrom(),
                'valid_until' => $membership->validUntil(),
            ],
        );
    }

    private function reconstitute(AdministrationMembershipRecord $record): AdministrationMembership
    {
        return new AdministrationMembership(
            new AdministrationMembershipId(new Uuid($record->getAttribute('id'))),
            new UserId(new Uuid($record->getAttribute('user_id'))),
            new AdministrationId(new Uuid($record->getAttribute('administration_id'))),
            $record->getAttribute('active'),
            new DateTimeImmutable($record->getAttribute('valid_from')->format(DATE_ATOM)),
            new DateTimeImmutable($record->getAttribute('valid_until')->format(DATE_ATOM)),
        );
    }
}
