<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Application\Fiscal\TaxPostingReadRepository;
use App\Application\Identity\PermissionAuthorizer;
use App\Application\Purchasing\CreatePurchaseCreditInvoice;
use App\Application\Purchasing\GetPurchaseCreditInvoice;
use App\Application\Purchasing\GetPurchaseCreditSourceSelection;
use App\Application\Purchasing\ListEligiblePurchaseCreditSources;
use App\Application\Purchasing\ListPurchaseCredits;
use App\Application\Purchasing\PurchaseCreditDraftInput;
use App\Application\Purchasing\PurchaseCreditMutationResult;
use App\Application\Purchasing\PurchaseCreditPostingRepository;
use App\Application\Purchasing\UpdateDraftPurchaseCreditInvoice;
use App\Domain\Fiscal\Enums\TaxSourceDocumentType;
use App\Domain\Fiscal\ValueObjects\TaxSourceDocumentId;
use App\Domain\Identity\Definitions\PurchasingPermission;
use App\Domain\Purchasing\Enums\PurchaseCreditInvoiceStatus;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseCreditInvoiceNumber;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceId;
use App\Domain\Purchasing\ValueObjects\PurchaseInvoiceLineId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseCreditRequest;
use App\Presentation\Formatting\DutchMoneyFormatter;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class PurchaseCreditController extends Controller
{
    public function __construct(private ListPurchaseCredits $list, private ListEligiblePurchaseCreditSources $sources, private GetPurchaseCreditSourceSelection $selection, private GetPurchaseCreditInvoice $get, private CreatePurchaseCreditInvoice $createCredit, private UpdateDraftPurchaseCreditInvoice $updateCredit, private PurchaseCreditPostingRepository $postings, private TaxPostingReadRepository $taxPostings, private PermissionAuthorizer $permissions, private DutchMoneyFormatter $money) {}

    public function index(Request $request): View
    {
        $context = $this->context($request);

        return view('purchasing.credits.index', $this->viewData($context) + ['credits' => $this->list->execute($context->administration->id()), 'postings' => $this->postingMap($context)]);
    }

    public function create(Request $request): View
    {
        $context = $this->context($request);
        $sourceId = $request->query('source');
        $selection = is_string($sourceId) && $sourceId !== '' ? $this->selection->execute($context->administration->id(), $this->invoiceId($sourceId)) : null;

        return view('purchasing.credits.create', $this->viewData($context) + ['sources' => $this->sources->execute($context->administration->id()), 'selection' => $selection]);
    }

    public function store(PurchaseCreditRequest $request): RedirectResponse
    {
        $context = $this->context($request);
        try {
            $result = $this->createCredit->execute($context->administration->id(), $this->input($request->validated()), $context->user->id());
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['credit' => 'De invoer bevat een ongeldige waarde.']);
        }
        if ($result->status !== PurchaseCreditMutationResult::Success || $result->id === null) {
            return back()->withInput()->withErrors(['credit' => $this->message($result->status)]);
        }

        return $this->redirect($context, $result->id)->with('status', 'Creditnota als Draft opgeslagen.');
    }

    public function show(Request $request, string $credit): View
    {
        $context = $this->context($request);
        $id = $this->creditId($credit);
        $detail = $this->get->execute($context->administration->id(), $id);
        abort_if($detail === null, 404);

        $taxPostings = $this->taxPostings->findForSource($context->administration->id(), TaxSourceDocumentType::PurchaseCreditInvoice, new TaxSourceDocumentId($id->uuid()));

        return view('purchasing.credits.show', $this->viewData($context) + ['credit' => $detail, 'posting' => $this->postings->findReadModel($context->administration->id(), $id), 'taxPostings' => $taxPostings]);
    }

    public function edit(Request $request, string $credit): View
    {
        $context = $this->context($request);
        $detail = $this->get->execute($context->administration->id(), $this->creditId($credit));
        abort_if($detail === null, 404);
        abort_if($detail->status() !== PurchaseCreditInvoiceStatus::Draft, 409);

        return view('purchasing.credits.edit', $this->viewData($context) + ['credit' => $detail, 'selection' => $this->selection->execute($context->administration->id(), $detail->sourcePurchaseInvoiceId())]);
    }

    public function update(PurchaseCreditRequest $request, string $credit): RedirectResponse
    {
        $context = $this->context($request);
        $id = $this->creditId($credit);
        try {
            $result = $this->updateCredit->execute($context->administration->id(), $id, $this->input($request->validated()));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['credit' => 'De invoer bevat een ongeldige waarde.']);
        }
        if ($result === PurchaseCreditMutationResult::NotFound) {
            abort(404);
        }
        if ($result !== PurchaseCreditMutationResult::Success) {
            return back()->withInput()->withErrors(['credit' => $this->message($result)]);
        }

        return $this->redirect($context, $id)->with('status', 'Creditnota bijgewerkt.');
    }

    private function input(array $data): PurchaseCreditDraftInput
    {
        return new PurchaseCreditDraftInput($this->invoiceId($data['source_invoice_id']), new PurchaseCreditInvoiceNumber($data['supplier_credit_invoice_number']), new DateTimeImmutable($data['supplier_credit_date']), new DateTimeImmutable($data['received_date']), array_map(fn (string $id) => new PurchaseInvoiceLineId(new Uuid($id)), $data['source_line_ids']));
    }

    private function postingMap(ActiveAdministrationContext $context): array
    {
        $result = [];
        foreach ($this->list->execute($context->administration->id()) as $credit) {
            $result[$credit->id()->toString()] = $this->postings->findReadModel($context->administration->id(), $credit->id());
        }

        return $result;
    }

    private function viewData(ActiveAdministrationContext $context): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context, 'canManageDrafts' => $this->can($context, PurchasingPermission::ManageCreditDrafts), 'canFinalize' => $this->can($context, PurchasingPermission::FinalizeCredits), 'canPost' => $this->can($context, PurchasingPermission::PostCredits), 'moneyFormatter' => $this->money];
    }

    private function message(PurchaseCreditMutationResult $result): string
    {
        return match ($result) {
            PurchaseCreditMutationResult::DuplicateSupplierCreditInvoice => 'Dit leverancierscreditnummer bestaat al voor deze leverancier.',
            PurchaseCreditMutationResult::InvalidSource => 'De geselecteerde bronfactuur is niet beschikbaar.',
            PurchaseCreditMutationResult::InvalidLines => 'Selecteer minimaal één beschikbare volledige bronregel.',
            default => 'De creditnota kan in de huidige status niet worden gewijzigd.',
        };
    }

    private function context(Request $request): ActiveAdministrationContext
    {
        return $request->attributes->get('administration_context');
    }

    private function can(ActiveAdministrationContext $context, PurchasingPermission $permission): bool
    {
        return $this->permissions->allows($context->permissionIds, $permission->id());
    }

    private function creditId(string $id): PurchaseCreditInvoiceId
    {
        try {
            return new PurchaseCreditInvoiceId(new Uuid($id));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function invoiceId(string $id): PurchaseInvoiceId
    {
        try {
            return new PurchaseInvoiceId(new Uuid($id));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function redirect(ActiveAdministrationContext $context, PurchaseCreditInvoiceId $id): RedirectResponse
    {
        return $this->can($context, PurchasingPermission::View) ? redirect()->route('purchasing.credits.show', $id->toString()) : redirect()->route('app');
    }
}
