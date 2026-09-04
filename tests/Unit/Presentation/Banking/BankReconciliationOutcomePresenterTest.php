<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Banking;

use App\Application\Banking\ReconcileBankStatementEntryStatus;
use App\Presentation\Banking\BankReconciliationOutcomePresenter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BankReconciliationOutcomePresenterTest extends TestCase
{
    #[Test]
    public function every_typed_denial_has_safe_dutch_feedback(): void
    {
        foreach (ReconcileBankStatementEntryStatus::cases() as $status) {
            self::assertNotSame('', BankReconciliationOutcomePresenter::message($status), $status->value);
        }
        self::assertStringContainsString('gelijktijdig gewijzigd', BankReconciliationOutcomePresenter::message(ReconcileBankStatementEntryStatus::ConcurrencyConflict));
        self::assertStringContainsString('gesloten', BankReconciliationOutcomePresenter::message(ReconcileBankStatementEntryStatus::PeriodClosed));
    }
}
