<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface DocumentMailTransport
{
    public function send(DocumentMailMessage $message): DocumentMailTransportResult;

    public function identifier(): string;
}
