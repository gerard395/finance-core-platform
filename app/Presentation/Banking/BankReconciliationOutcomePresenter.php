<?php

declare(strict_types=1);

namespace App\Presentation\Banking;

use App\Application\Banking\ReconcileBankStatementEntryStatus;

final class BankReconciliationOutcomePresenter
{
    public static function message(ReconcileBankStatementEntryStatus $status): string
    {
        return match ($status) {
            ReconcileBankStatementEntryStatus::Ignored => 'Herstel deze genegeerde bankmutatie voordat u boekt.',
            ReconcileBankStatementEntryStatus::AlreadyReconciled => 'Deze bankmutatie is al gereconciled.',
            ReconcileBankStatementEntryStatus::InvalidIntent => 'De gekozen intentie is ongeldig voor deze bankmutatie.',
            ReconcileBankStatementEntryStatus::InvalidAllocation => 'De gekozen allocaties zijn ongeldig.',
            ReconcileBankStatementEntryStatus::AllocationIncomplete => 'De allocaties moeten samen exact het bankbedrag vormen.',
            ReconcileBankStatementEntryStatus::AllocationExceedsOpenBalance => 'Een allocatie overschrijdt het actuele openstaande saldo.',
            ReconcileBankStatementEntryStatus::RelationMismatch => 'Alle allocaties moeten bij dezelfde relatie horen.',
            ReconcileBankStatementEntryStatus::MissingPostingConfiguration => 'De bankboekingsconfiguratie is niet volledig.',
            ReconcileBankStatementEntryStatus::InvalidContraAccount => 'De geselecteerde tegenrekening is niet toegestaan.',
            ReconcileBankStatementEntryStatus::PeriodClosed => 'De boekingsperiode voor deze bankmutatie is gesloten.',
            ReconcileBankStatementEntryStatus::NoAccountingPeriod => 'Er is geen boekingsperiode voor de gekozen PostingDate ingericht.',
            ReconcileBankStatementEntryStatus::PeriodIntegrityFailure => 'De periodenindeling is inconsistent; boeken is veilig geblokkeerd.',
            ReconcileBankStatementEntryStatus::FinancialStateInvalid => 'De financiële toestand is inconsistent; boeken is veilig geblokkeerd.',
            ReconcileBankStatementEntryStatus::PostingFailure => 'De financiële boeking kon niet atomair worden afgerond.',
            ReconcileBankStatementEntryStatus::ConcurrencyConflict => 'De bankmutatie is gelijktijdig gewijzigd. Ververs en controleer opnieuw.',
            default => 'De bankmutatie kon niet worden gereconciled.',
        };
    }
}
