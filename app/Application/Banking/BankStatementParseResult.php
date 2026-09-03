<?php

declare(strict_types=1);

namespace App\Application\Banking;

use App\Domain\Banking\Entities\BankStatement;
use App\Domain\Banking\ValueObjects\CamtNamespaceVersion;
use App\Domain\Banking\ValueObjects\OriginalFileHash;

final readonly class BankStatementParseResult
{
    /** @param list<BankStatement> $statements */
    private function __construct(public BankStatementParseStatus $status, public array $statements = [], public ?CamtNamespaceVersion $namespace = null, public ?OriginalFileHash $originalFileHash = null) {}

    /** @param list<BankStatement> $statements */
    public static function success(array $statements, CamtNamespaceVersion $namespace, OriginalFileHash $hash): self
    {
        return new self(BankStatementParseStatus::Success, $statements, $namespace, $hash);
    }

    public static function failure(BankStatementParseStatus $status, ?OriginalFileHash $hash = null): self
    {
        return new self($status, originalFileHash: $hash);
    }
}
