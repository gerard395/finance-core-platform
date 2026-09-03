<?php

declare(strict_types=1);

namespace App\Application\Banking;

interface BankStatementParser
{
    public function parse(string $bytes, ?string $expectedAccountIdentity = null): BankStatementParseResult;
}
