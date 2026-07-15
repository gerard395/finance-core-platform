<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class RelationTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $relation = $this->createRelation();

        self::assertSame('RELATION_001', $relation->code()->value());
        self::assertSame('Finance Supplier', $relation->displayName()->value());
        self::assertTrue($relation->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity_or_code(): void
    {
        $relation = $this->createRelation();
        $id = $relation->id();
        $code = $relation->code();

        $relation->rename(new DisplayName('Renamed Relation'));

        self::assertSame('Renamed Relation', $relation->displayName()->value());
        self::assertSame($id, $relation->id());
        self::assertSame($code, $relation->code());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $relation = $this->createRelation();

        $relation->deactivate();
        $relation->deactivate();
        self::assertFalse($relation->isActive());

        $relation->activate();
        $relation->activate();
        self::assertTrue($relation->isActive());
    }

    private function createRelation(): Relation
    {
        return new Relation(
            new RelationId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new RelationCode('relation_001'),
            new DisplayName('Finance Supplier'),
            true,
        );
    }
}
