<?php

declare(strict_types=1);

namespace App\Application\Sales;

use RuntimeException;

final class SalesInvoicePersistenceConflict extends RuntimeException
{
    public function __construct(private readonly SalesInvoiceWriteResult $result)
    {
        parent::__construct('Sales invoice persistence conflict.');
    }

    public function result(): SalesInvoiceWriteResult
    {
        return $this->result;
    }
}
