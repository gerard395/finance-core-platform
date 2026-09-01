<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Application\Banking\BankTransactionReversalEligibilityStatus;
use App\Application\Banking\GetBankTransactionWebDetail;
use App\Application\Banking\ReverseBankTransaction;
use App\Application\Banking\ReverseBankTransactionStatus;
use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankPaymentReversalRequest;
use App\Presentation\Formatting\DutchMoneyFormatter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class BankPaymentReversalController extends Controller
{
    public function __construct(private GetBankTransactionWebDetail $details, private ReverseBankTransaction $reverse, private PermissionAuthorizer $permissions, private DutchMoneyFormatter $money) {}

    public function create(Request $request, string $payment): View|RedirectResponse
    {
        $context = $this->context($request);
        abort_unless($this->permissions->allows($context->permissionIds, BankingPermission::ReversePayments->id()), 403);
        $detail = $this->details->execute($context->administration->id(), $this->id($payment));
        abort_if($detail === null, 404);
        if ($detail->reversal->status !== BankTransactionReversalEligibilityStatus::Eligible) {
            return redirect()->route('banking.payments.show', $payment)->with('error', $this->eligibilityMessage($detail->reversal->status));
        }

        return view('banking.payments.reverse', ['domainUser' => $context->user, 'administrationContext' => $context, 'detail' => $detail, 'moneyFormatter' => $this->money]);
    }

    public function store(BankPaymentReversalRequest $request, string $payment): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($payment);
        try {
            $data = $request->validated();
            $result = $this->reverse->execute($context->administration->id(), $id, new PostingDate(new DateTimeImmutable($data['reversal_posting_date'])), BankTransactionReversalReason::fromUserInput($data['reason']), $context->user->id());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['reversal' => 'De invoer bevat een ongeldige waarde.']);
        }
        if ($result->status === ReverseBankTransactionStatus::NotFound) {
            abort(404);
        }
        [$key, $message] = match ($result->status) {
            ReverseBankTransactionStatus::Success => ['status', 'De bankbetaling is teruggedraaid.'],
            ReverseBankTransactionStatus::AlreadyReversed => ['status', 'Deze bankbetaling is al teruggedraaid.'],
            ReverseBankTransactionStatus::NotPosted => ['error', 'Alleen een geboekte bankbetaling kan worden teruggedraaid.'],
            ReverseBankTransactionStatus::FinancialStateInvalid => ['error', 'De financiële status is gewijzigd of niet consistent. Controleer de betaling opnieuw.'],
            ReverseBankTransactionStatus::PeriodClosed => ['error', 'De boekingsperiode voor deze terugboeking is gesloten.'],
            ReverseBankTransactionStatus::NoAccountingPeriod => ['error', 'Voor de terugboekingsdatum is geen boekingsperiode ingericht.'],
            ReverseBankTransactionStatus::PeriodIntegrityFailure => ['error', 'De boekingsperiode is niet eenduidig. Controle is vereist.'],
            ReverseBankTransactionStatus::PostingFailure => ['error', 'De bankbetaling kon niet worden teruggedraaid. Probeer het later opnieuw.'],
            ReverseBankTransactionStatus::NotFound => throw new InvalidArgumentException('Handled above.'),
        };

        return $this->redirect($context, $id)->with($key, $message);
    }

    private function eligibilityMessage(BankTransactionReversalEligibilityStatus $status): string
    {
        return match ($status) {
            BankTransactionReversalEligibilityStatus::AlreadyReversed => 'Deze bankbetaling is al teruggedraaid.',
            BankTransactionReversalEligibilityStatus::NotPosted => 'Alleen een geboekte bankbetaling kan worden teruggedraaid.',
            default => 'Deze bankbetaling kan door de huidige financiële status niet worden teruggedraaid.',
        };
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function id(string $value): BankTransactionId
    {
        try {
            return new BankTransactionId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function redirect(ActiveAdministrationContext $context, BankTransactionId $id): RedirectResponse
    {
        return $this->permissions->allows($context->permissionIds, BankingPermission::View->id()) ? redirect()->route('banking.payments.show', $id->toString()) : redirect()->route('app');
    }
}
