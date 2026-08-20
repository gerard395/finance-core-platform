<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Relations;

use App\Application\Relations\GetRelationDetail;
use App\Application\Relations\RelationClassification;
use App\Application\Relations\RelationClassificationReader;
use App\Application\Relations\RelationReadRepository;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Entities\Relation;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class GetRelationDetailTest extends TestCase
{
    public function test_it_returns_typed_detail_for_the_requested_administration_and_relation(): void
    {
        $administrationId = $this->administrationId('1');
        $relationId = $this->relationId('1');
        $relation = new Relation($relationId, new RelationCode('REL-1'), new DisplayName('Relation One'), false);
        $relations = $this->relations($administrationId, $relationId, $relation);
        $classifications = $this->classifications($administrationId, $relationId, new RelationClassification(true, true));

        $detail = (new GetRelationDetail($relations, $classifications))->execute($administrationId, $relationId);

        self::assertNotNull($detail);
        self::assertTrue($detail->id()->equals($relationId));
        self::assertSame('REL-1', $detail->code()->toString());
        self::assertSame('Relation One', $detail->displayName()->toString());
        self::assertFalse($detail->isActive());
        self::assertTrue($detail->isCustomer());
        self::assertTrue($detail->isSupplier());
    }

    public function test_it_returns_null_without_classification_lookup_when_relation_is_not_found(): void
    {
        $administrationId = $this->administrationId('1');
        $relationId = $this->relationId('1');
        $classifications = new class implements RelationClassificationReader
        {
            public function classify(AdministrationId $administrationId, RelationId $relationId): RelationClassification
            {
                TestCase::fail('Classification must not be queried for a missing Relation.');
            }
        };

        $result = (new GetRelationDetail($this->relations($administrationId, $relationId, null), $classifications))->execute($administrationId, $relationId);

        self::assertNull($result);
    }

    public function test_it_preserves_inactive_classification_result(): void
    {
        $administrationId = $this->administrationId('1');
        $relationId = $this->relationId('1');
        $relation = new Relation($relationId, new RelationCode('REL-1'), new DisplayName('Neither'), true);
        $detail = (new GetRelationDetail(
            $this->relations($administrationId, $relationId, $relation),
            $this->classifications($administrationId, $relationId, new RelationClassification(false, false)),
        ))->execute($administrationId, $relationId);

        self::assertNotNull($detail);
        self::assertFalse($detail->isCustomer());
        self::assertFalse($detail->isSupplier());
    }

    private function relations(AdministrationId $expectedAdministrationId, RelationId $expectedRelationId, ?Relation $result): RelationReadRepository
    {
        return new class($expectedAdministrationId, $expectedRelationId, $result) implements RelationReadRepository
        {
            public function __construct(
                private readonly AdministrationId $expectedAdministrationId,
                private readonly RelationId $expectedRelationId,
                private readonly ?Relation $result,
            ) {}

            public function findByIdForAdministration(AdministrationId $administrationId, RelationId $relationId): ?Relation
            {
                TestCase::assertTrue($administrationId->equals($this->expectedAdministrationId));
                TestCase::assertTrue($relationId->equals($this->expectedRelationId));

                return $this->result;
            }

            public function findForAdministration(AdministrationId $administrationId): array
            {
                TestCase::fail('List lookup is outside the detail use case.');
            }
        };
    }

    private function classifications(AdministrationId $expectedAdministrationId, RelationId $expectedRelationId, RelationClassification $result): RelationClassificationReader
    {
        return new class($expectedAdministrationId, $expectedRelationId, $result) implements RelationClassificationReader
        {
            public function __construct(
                private readonly AdministrationId $expectedAdministrationId,
                private readonly RelationId $expectedRelationId,
                private readonly RelationClassification $result,
            ) {}

            public function classify(AdministrationId $administrationId, RelationId $relationId): RelationClassification
            {
                TestCase::assertTrue($administrationId->equals($this->expectedAdministrationId));
                TestCase::assertTrue($relationId->equals($this->expectedRelationId));

                return $this->result;
            }
        };
    }

    private function administrationId(string $suffix): AdministrationId
    {
        return new AdministrationId(new Uuid('10000000-0000-4000-8000-00000000000'.$suffix));
    }

    private function relationId(string $suffix): RelationId
    {
        return new RelationId(new Uuid('60000000-0000-4000-8000-00000000000'.$suffix));
    }
}
