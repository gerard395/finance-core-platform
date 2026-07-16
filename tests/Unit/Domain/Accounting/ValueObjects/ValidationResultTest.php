<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Accounting\ValueObjects;

use App\Domain\Accounting\ValueObjects\ValidationError;
use App\Domain\Accounting\ValueObjects\ValidationResult;
use PHPUnit\Framework\TestCase;

final class ValidationResultTest extends TestCase
{
    public function test_an_empty_result_is_valid(): void
    {
        $result = new ValidationResult;

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());
    }

    public function test_a_result_with_errors_is_invalid_and_exposes_error_values(): void
    {
        $error = new ValidationError('minimum_lines', 'At least two lines are required.');
        $result = new ValidationResult([$error]);

        self::assertFalse($result->isValid());
        self::assertSame([$error], $result->errors());
        self::assertSame('minimum_lines', $error->code());
        self::assertSame('At least two lines are required.', $error->message());
    }

    public function test_the_error_collection_is_defensively_owned(): void
    {
        $error = new ValidationError('minimum_lines', 'At least two lines are required.');
        $errors = [$error];
        $result = new ValidationResult($errors);
        $errors[] = new ValidationError('unbalanced_entry', 'Entry is not balanced.');

        self::assertSame([$error], $result->errors());
    }
}
