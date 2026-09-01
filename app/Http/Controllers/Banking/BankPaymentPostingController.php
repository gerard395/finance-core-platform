<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Application\Banking\PostBankTransaction;
use App\Application\Banking\PostBankTransactionStatus;
use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class BankPaymentPostingController extends Controller
{
    public function __construct(private PostBankTransaction $postTransaction, private PermissionAuthorizer $permissions) {}

    public function __invoke(Request $request, string $payment): RedirectResponse
    {
        $validated = $request->validate(['posting_date' => ['required', 'date_format:Y-m-d']]);
        $context = $request->attributes->get('administration_context');
        $id = $this->id($payment);
        $result = $this->postTransaction->execute($context->administration->id(), $id, new PostingDate(new DateTimeImmutable($validated['posting_date'])), $context->user->id());
        if ($result === PostBankTransactionStatus::NotFound) {
            abort(404);
        }
        [$key, $message] = match ($result) {
            PostBankTransactionStatus::Success => ['status', 'Bankbetaling is geboekt.'],
            PostBankTransactionStatus::AlreadyPosted => ['status', 'Deze bankbetaling is al geboekt.'],
            PostBankTransactionStatus::ConfigurationMissing => ['error', 'De bankboekingsinstellingen voor deze bankrekening zijn nog niet ingericht.'],
            PostBankTransactionStatus::ConfigurationInvalid => ['error', 'De bankboekingsinstellingen verwijzen naar een inactief of ongeldig dagboek of rekening.'],
            PostBankTransactionStatus::AllocationExceedsOpenBalance => ['error', 'Een van de openstaande posten heeft inmiddels onvoldoende openstaand saldo. Controleer de betaling opnieuw.'],
            PostBankTransactionStatus::InvalidState => ['error', 'Deze bankbetaling kan in de huidige status niet worden geboekt.'],
            PostBankTransactionStatus::FinancialStateInvalid => ['error', 'De financiële gegevens van deze bankbetaling zijn niet consistent.'],
            PostBankTransactionStatus::PeriodClosed => ['error', 'De boekingsperiode voor deze bankbetaling is gesloten.'],
            PostBankTransactionStatus::NoAccountingPeriod => ['error', 'Voor de boekingsdatum is geen boekingsperiode ingericht.'],
            PostBankTransactionStatus::PeriodIntegrityFailure => ['error', 'De boekingsperiode is niet eenduidig. Controle is vereist.'],
            default => ['error', 'De bankbetaling kon niet worden geboekt. Probeer het later opnieuw.'],
        };

        return ($this->permissions->allows($context->permissionIds, BankingPermission::View->id()) ? redirect()->route('banking.payments.show', $id->toString()) : redirect()->route('app'))->with($key, $message);
    }

    private function id(string $value): BankTransactionId
    {
        try {
            return new BankTransactionId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
