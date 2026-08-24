<?php

declare(strict_types=1);

namespace App\Application\Administration;

enum AdministrationSettingsWriteResult
{
    case Success;
    case NotFound;
}
