<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Accounting\Requests\PostingRequest;
use App\Domain\Accounting\ValueObjects\PostingResult;
use App\Domain\Fiscal\Entities\TaxPosting;

final readonly class SalesCreditFiscalPostingResult
{
    /** @param list<TaxPosting> $taxPostings */
    public function __construct(
        private PostingRequest $postingRequest,
        private PostingResult $postingResult,
        private array $taxPostings,
    ) {}

    public function postingRequest(): PostingRequest
    {
        return $this->postingRequest;
    }

    public function postingResult(): PostingResult
    {
        return $this->postingResult;
    }

    /** @return list<TaxPosting> */
    public function taxPostings(): array
    {
        return $this->taxPostings;
    }
}
