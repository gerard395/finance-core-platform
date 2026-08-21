<?php

declare(strict_types=1);

namespace App\Application\Sales;

use RuntimeException;

final class QuotationPersistenceConflict extends RuntimeException
{
    public function __construct(private readonly QuotationWriteResult $result)
    {
        parent::__construct('Quotation persistence conflict.');
    }

    public function result(): QuotationWriteResult
    {
        return $this->result;
    }
}
