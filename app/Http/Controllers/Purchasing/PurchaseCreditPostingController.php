<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Purchasing\PostPurchaseCreditInvoice;
use App\Application\Purchasing\PostPurchaseCreditInvoiceResult;
use App\Application\Purchasing\PostPurchaseCreditInvoiceStatus;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Controllers\Controller;
use App\Presentation\Formatting\DutchMoneyFormatter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PurchaseCreditPostingController extends Controller
{
    public function __construct(private PostPurchaseCreditInvoice $postCredit, private PermissionAuthorizer $permissions, private DutchMoneyFormatter $money) {}

    public function __invoke(Request $request, string $credit): RedirectResponse
    {
        $validated = $request->validate(['posting_date' => ['required', 'date_format:Y-m-d']]);
        $context = $request->attributes->get('administration_context');
        $id = $this->id($credit);
        $result = $this->postCredit->execute($context->administration->id(), $id, new PostingDate(new DateTimeImmutable($validated['posting_date'])), $context->user->id());
        if ($result->status === PostPurchaseCreditInvoiceStatus::NotFound) {
            abort(404);
        }
        [$key, $message] = match ($result->status) {
            PostPurchaseCreditInvoiceStatus::Success => ['status', $this->successMessage($result)],
            PostPurchaseCreditInvoiceStatus::AlreadyPosted => ['status', 'Deze creditnota is al geboekt.'],
            PostPurchaseCreditInvoiceStatus::SourceLineAlreadyCredited => ['error', 'Een geselecteerde bronregel is inmiddels door een andere creditnota gecrediteerd.'],
            PostPurchaseCreditInvoiceStatus::InvalidState => ['error', 'Alleen een gefinaliseerde creditnota kan worden geboekt.'],
            PostPurchaseCreditInvoiceStatus::FinancialStateInvalid => ['error', 'De historische financiële brongegevens zijn niet meer consistent.'],
            PostPurchaseCreditInvoiceStatus::PeriodClosed => ['error', 'De boekingsperiode voor deze creditnota is gesloten.'],
            PostPurchaseCreditInvoiceStatus::NoAccountingPeriod => ['error', 'Voor de boekingsdatum is geen boekingsperiode ingericht.'],
            PostPurchaseCreditInvoiceStatus::PeriodIntegrityFailure => ['error', 'De boekingsperiode is niet eenduidig. Controle is vereist.'],
            default => ['error', 'De creditnota kon niet volledig worden geboekt en verrekend. Probeer het later opnieuw.'],
        };

        $response = ($this->permissions->allows($context->permissionIds, PurchasingPermission::View->id()) ? redirect()->route('purchasing.credits.show', $id->toString()) : redirect()->route('app'))->with($key, $message);

        return $key === 'error' ? $response->withInput($request->only('posting_date')) : $response;
    }

    private function successMessage(PostPurchaseCreditInvoiceResult $result): string
    {
        if ($result->matchedAmount === null || $result->creditRemainingAmount === null) {
            return 'Creditnota is geboekt.';
        }

        if ($result->matchedAmount->isZero()) {
            $message = 'Creditnota is geboekt. Er is geen bedrag automatisch met de bronfactuur verrekend.';

            return $result->creditRemainingAmount->isPositive()
                ? $message.' Leverancierscreditsaldo: '.$this->money->format($result->creditRemainingAmount).'.'
                : $message;
        }

        if ($result->creditRemainingAmount->isZero()) {
            return 'Creditnota is geboekt en volledig met de bronfactuur verrekend. Automatisch verrekend: '.$this->money->format($result->matchedAmount).'.';
        }

        return 'Creditnota is geboekt en gedeeltelijk met de bronfactuur verrekend. Automatisch verrekend: '.$this->money->format($result->matchedAmount).'. Leverancierscreditsaldo: '.$this->money->format($result->creditRemainingAmount).'.';
    }

    private function id(string $value): PurchaseCreditInvoiceId
    {
        try {
            return new PurchaseCreditInvoiceId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
