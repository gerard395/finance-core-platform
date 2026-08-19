<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fiscal\Services;

use App\Domain\Fiscal\Services\TaxPostingIdentityPolicy;
use App\Domain\Fiscal\ValueObjects\TaxPostingId;
use App\Domain\Shared\Identity\Uuid;
use DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Domain\Fiscal\Entities\TaxPostingTest;

final class TaxPostingIdentityPolicyTest extends TestCase
{
    public function test_available_identity_is_accepted_without_mutating_history(): void
    {
        $history = [];
        (new TaxPostingIdentityPolicy)->assertNewIdAvailable($this->id(1), $history);
        self::assertSame([], $history);
    }

    public function test_collision_with_existing_original_or_reversal_is_rejected(): void
    {
        $posting = (new \ReflectionClass(TaxPostingTest::class));
        self::assertNotNull($posting);
        $this->expectException(DomainException::class);
        $fake = new class($this->id(1))
        {
            public function __construct(private TaxPostingId $id) {}

            public function id(): TaxPostingId
            {
                return $this->id;
            }
        };
        (new TaxPostingIdentityPolicy)->assertNewIdAvailable($this->id(1), [$fake]);
    }

    private function id(int $n): TaxPostingId
    {
        return new TaxPostingId(new Uuid(sprintf('10000000-0000-4000-8000-%012d', $n)));
    }
}
