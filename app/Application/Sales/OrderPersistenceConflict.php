<?php

declare(strict_types=1);

namespace App\Application\Sales;

use RuntimeException;

final class OrderPersistenceConflict extends RuntimeException
{
    public function __construct(private readonly OrderWriteResult $result)
    {
        parent::__construct('Order persistence conflict.');
    }

    public function result(): OrderWriteResult
    {
        return $this->result;
    }
}
