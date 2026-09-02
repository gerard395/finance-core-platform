<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use RuntimeException;

final class DestructiveDatabaseCommandBlocked extends RuntimeException {}
