<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Application\Banking\BankEntryDerivedState;
use App\Application\Banking\BankReconciliationSourceItem;
use App\Application\Banking\BankReconciliationSourceReader;
use App\Application\Banking\BankReconciliationWorklistFilter;
use App\Application\Banking\ReverseBankTransactionStatus;
use App\Application\Banking\ReverseReconciledBankTransaction;
use App\Domain\Accounting\ValueObjects\PostingDate;
use App\Domain\Banking\ValueObjects\BankStatementEntryId;
use App\Domain\Banking\ValueObjects\BankTransactionReversalReason;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankPaymentReversalRequest;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class ImportedBankEntryReversalController extends Controller
{
    public function __construct(private readonly BankReconciliationSourceReader $sources, private readonly ReverseReconciledBankTransaction $reverse) {}

    public function create(Request $request, string $entry): View
    {
        $context = $this->context($request);
        $source = $this->source($context, $entry);
        abort_unless($source->state === BankEntryDerivedState::Reconciled && $source->financial !== null, 409);

        return view('banking.reconciliation.reverse', ['domainUser' => $context->user, 'administrationContext' => $context, 'source' => $source]);
    }

    public function store(BankPaymentReversalRequest $request, string $entry): RedirectResponse
    {
        $context = $this->context($request);
        $source = $this->source($context, $entry);
        abort_if($source->financial === null, 409);
        try {
            $data = $request->validated();
            $result = $this->reverse->execute($context->administration->id(), $source->financial->bankTransactionId, new PostingDate(new DateTimeImmutable($data['reversal_posting_date'])), BankTransactionReversalReason::fromUserInput($data['reason']), $context->user->id());
        } catch (InvalidArgumentException) {
            return back()->withInput($request->only('reversal_posting_date'))->with('error', 'De invoer voor de correctie is ongeldig.');
        }
        if ($result->status === ReverseBankTransactionStatus::NotFound) {
            abort(404);
        }
        if ($result->status !== ReverseBankTransactionStatus::Success) {
            return back()->withInput($request->only('reversal_posting_date'))->with('error', match ($result->status) {
                ReverseBankTransactionStatus::AlreadyReversed => 'Deze reconciliation is al teruggedraaid.',
                ReverseBankTransactionStatus::NotPosted => 'Alleen een geboekte reconciliation kan worden teruggedraaid.',
                ReverseBankTransactionStatus::FinancialStateInvalid => 'De financiële graph is niet coherent; correctie is veilig geblokkeerd.',
                ReverseBankTransactionStatus::PeriodClosed => 'De boekingsperiode voor de correctie is gesloten.',
                ReverseBankTransactionStatus::NoAccountingPeriod => 'Er is geen boekingsperiode voor de correctiedatum.',
                ReverseBankTransactionStatus::PeriodIntegrityFailure => 'De periodenindeling is inconsistent.',
                default => 'De correctie kon niet atomair worden afgerond.',
            });
        }

        return redirect()->route('banking.reconciliation.show', $entry)->with('status', 'De reconciliation is via een historische contra-boeking teruggedraaid.');
    }

    private function source(ActiveAdministrationContext $context, string $entry): BankReconciliationSourceItem
    {
        $id = $this->entryId($entry);
        foreach ($this->sources->list($context->administration->id(), new BankReconciliationWorklistFilter(states: BankEntryDerivedState::cases())) as $source) {
            if ($source->entry->id->equals($id)) {
                return $source;
            }
        }
        abort(404);
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function entryId(string $value): BankStatementEntryId
    {
        try {
            return new BankStatementEntryId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
