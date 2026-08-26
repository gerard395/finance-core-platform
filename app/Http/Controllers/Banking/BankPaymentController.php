<?php

declare(strict_types=1);

namespace App\Http\Controllers\Banking;

use App\Application\Banking\BankTransactionAllocationInput;
use App\Application\Banking\BankTransactionIdentityGenerator;
use App\Application\Banking\BankTransactionResult;
use App\Application\Banking\CreateManualBankTransaction;
use App\Application\Banking\GetBankPaymentMasterData;
use App\Application\Banking\GetBankTransactionWebDetail;
use App\Application\Banking\ListBankTransactionWebDetails;
use App\Application\Banking\UpdateDraftBankTransaction;
use App\Application\Identity\PermissionAuthorizer;
use App\Domain\Accounting\ValueObjects\OpenItemId;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\ValueObjects\AdministrationBankAccountId;
use App\Domain\Banking\ValueObjects\BankTransactionId;
use App\Domain\Banking\ValueObjects\BankTransactionReference;
use App\Domain\Banking\ValueObjects\PaymentAllocationId;
use App\Domain\Banking\ValueObjects\TransactionDate;
use App\Domain\Banking\ValueObjects\TransactionDescription;
use App\Domain\Identity\Definitions\AdministrationPermission;
use App\Domain\Identity\Definitions\BankingPermission;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Finance\Currency;
use App\Domain\Shared\Finance\Money;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankTransactionRequest;
use App\Presentation\Formatting\DutchMoneyFormatter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class BankPaymentController extends Controller
{
    public function __construct(private ListBankTransactionWebDetails $list, private GetBankTransactionWebDetail $details, private GetBankPaymentMasterData $masterData, private CreateManualBankTransaction $createTransaction, private UpdateDraftBankTransaction $updateTransaction, private BankTransactionIdentityGenerator $ids, private PermissionAuthorizer $permissions, private DutchMoneyFormatter $money) {}

    public function index(Request $request): View
    {
        $context = $this->context($request);

        return view('banking.payments.index', $this->viewData($context) + ['payments' => $this->list->execute($context->administration->id())]);
    }

    public function show(Request $request, string $payment): View
    {
        $context = $this->context($request);
        $detail = $this->details->execute($context->administration->id(), $this->id($payment));
        abort_if($detail === null, 404);

        return view('banking.payments.show', $this->viewData($context) + ['detail' => $detail]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request);

        return view('banking.payments.create', $this->viewData($context) + ['masterData' => $this->masterData->execute($context->administration->id())]);
    }

    public function store(BankTransactionRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        try {
            $data = $request->validated();
            [$result, $id] = $this->createTransaction->execute($context->administration->id(), new AdministrationBankAccountId(new Uuid($data['bank_account_id'])), new TransactionDate(new DateTimeImmutable($data['transaction_date'])), $this->signedMoney($data['payment_type'], $data['amount']), new BankTransactionReference(trim($data['reference'])), new TransactionDescription(trim($data['description'])), new RelationId(new Uuid($data['relation_id'])), $context->user->id(), $this->allocations($data['allocations'] ?? []));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['payment' => 'De invoer bevat een ongeldige waarde.']);
        }
        if ($result !== BankTransactionResult::Success || $id === null) {
            return back()->withInput()->withErrors(['payment' => $this->writeError($result)]);
        }

        return $this->redirect($context, $id)->with('status', 'Bankbetaling als Draft aangemaakt.');
    }

    public function edit(Request $request, string $payment): View
    {
        $context = $this->context($request);
        $detail = $this->details->execute($context->administration->id(), $this->id($payment));
        abort_if($detail === null, 404);
        abort_if($detail->transaction->status() !== BankTransactionStatus::Draft, 409);

        return view('banking.payments.edit', $this->viewData($context) + ['detail' => $detail, 'masterData' => $this->masterData->execute($context->administration->id())]);
    }

    public function update(BankTransactionRequest $request, string $payment): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->id($payment);
        try {
            $data = $request->validated();
            $result = $this->updateTransaction->execute($context->administration->id(), $id, new AdministrationBankAccountId(new Uuid($data['bank_account_id'])), new TransactionDate(new DateTimeImmutable($data['transaction_date'])), $this->signedMoney($data['payment_type'], $data['amount']), new BankTransactionReference(trim($data['reference'])), new TransactionDescription(trim($data['description'])), new RelationId(new Uuid($data['relation_id'])), $this->allocations($data['allocations'] ?? []));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['payment' => 'De invoer bevat een ongeldige waarde.']);
        }
        if ($result === BankTransactionResult::NotFound) {
            abort(404);
        }
        if ($result !== BankTransactionResult::Success) {
            return back()->withInput()->withErrors(['payment' => $this->writeError($result)]);
        }

        return $this->redirect($context, $id)->with('status', 'Bankbetaling bijgewerkt.');
    }

    private function allocations(array $rows): array
    {
        return array_map(fn (array $row): BankTransactionAllocationInput => new BankTransactionAllocationInput(
            empty($row['allocation_id']) ? $this->ids->allocation() : new PaymentAllocationId(new Uuid($row['allocation_id'])),
            new OpenItemId(new Uuid($row['open_item_id'])),
            new Money($row['amount'], new Currency('EUR')),
        ), $rows);
    }

    private function signedMoney(string $type, string $amount): Money
    {
        return new Money($type === 'supplier_payment' ? '-'.$amount : $amount, new Currency('EUR'));
    }

    private function writeError(BankTransactionResult $result): string
    {
        return match ($result) {
            BankTransactionResult::InvalidReference => 'De geselecteerde bankrekening of relatie is niet beschikbaar.',
            BankTransactionResult::InvalidAllocation => 'De allocaties zijn ongeldig.',
            BankTransactionResult::InvalidState => 'Deze bankbetaling kan in de huidige status niet worden gewijzigd.',
            default => 'De bankbetaling kon niet worden opgeslagen.',
        };
    }

    private function viewData(ActiveAdministrationContext $context): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context, 'canManage' => $this->permissions->allows($context->permissionIds, BankingPermission::ManagePayments->id()), 'canPost' => $this->permissions->allows($context->permissionIds, BankingPermission::PostPayments->id()), 'canUpdateSettings' => $this->permissions->allows($context->permissionIds, AdministrationPermission::UpdateSettings->id()), 'moneyFormatter' => $this->money];
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
