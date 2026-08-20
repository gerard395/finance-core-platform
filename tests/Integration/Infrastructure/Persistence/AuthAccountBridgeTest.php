<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Domain\Identity\Enums\UserStatus;
use App\Infrastructure\Persistence\Eloquent\Models\DomainUserRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthAccountBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_user_carries_one_required_domain_user_uuid(): void
    {
        $authUser = User::factory()->create();

        self::assertTrue(Str::isUuid($authUser->domain_user_id));
        self::assertTrue(DomainUserRecord::query()->whereKey($authUser->domain_user_id)->exists());
    }

    public function test_duplicate_domain_user_id_is_rejected_by_the_database(): void
    {
        $domainUserId = $this->createDomainUser();
        User::factory()->create(['domain_user_id' => $domainUserId]);

        $this->expectException(QueryException::class);

        User::factory()->create(['domain_user_id' => $domainUserId]);
    }

    public function test_domain_user_storage_contains_no_authentication_columns(): void
    {
        self::assertFalse(Schema::hasColumn('domain_users', 'password'));
        self::assertFalse(Schema::hasColumn('domain_users', 'remember_token'));
        self::assertFalse(Schema::hasColumn('domain_users', 'email_verified_at'));
    }

    private function createDomainUser(): string
    {
        $id = (string) Str::uuid();

        DomainUserRecord::query()->create([
            'id' => $id,
            'display_name' => 'Bridge User',
            'email' => Str::uuid().'@example.com',
            'status' => UserStatus::Active->value,
        ]);

        return $id;
    }
}
