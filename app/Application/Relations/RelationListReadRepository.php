<?php

declare(strict_types=1);

namespace App\Application\Relations;

interface RelationListReadRepository
{
    public function search(RelationListQuery $query): PaginatedRelationList;
}
