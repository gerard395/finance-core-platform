<?php

declare(strict_types=1);

namespace App\Application\Accounting;

use App\Domain\Accounting\ValueObjects\OpenItemMatchId;

final readonly class MatchOpenItemsResult
{
    public function __construct(public MatchOpenItemsStatus $status, public ?OpenItemMatchId $matchId = null) {}
}
