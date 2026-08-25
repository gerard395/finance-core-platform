<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\AddressReadRepository;
use App\Application\Relations\BankAccountReadRepository;
use App\Application\Relations\ContactReadRepository;
use App\Application\Relations\GetRelationDetail;
use App\Application\Sales\SalesDocumentRecipientPurpose;
use App\Application\Sales\SalesDocumentRecipientReader;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Controllers\Controller;
use App\Presentation\Relations\AddressTypePresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

final class RelationShowController extends Controller
{
    public function __construct(
        private readonly GetRelationDetail $getRelationDetail,
        private readonly ContactReadRepository $contacts,
        private readonly AddressReadRepository $addresses,
        private readonly BankAccountReadRepository $bankAccounts,
        private readonly PermissionAuthorizer $permissionAuthorizer,
        private readonly SalesDocumentRecipientReader $documentRecipients,
    ) {}

    public function __invoke(Request $request, string $relation): View
    {
        try {
            $relationId = new RelationId(new Uuid($relation));
        } catch (InvalidArgumentException) {
            abort(404);
        }

        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $detail = $this->getRelationDetail->execute($context->administration->id(), $relationId);

        abort_if($detail === null, 404);

        $contacts = array_values(array_filter(array_map(
            fn ($contact) => $this->contacts->findForRelation($context->administration->id(), $relationId, $contact->id),
            $this->contacts->listForRelation($context->administration->id(), $relationId),
        )));

        return view('relations.show', [
            'domainUser' => $context->user,
            'administrationContext' => $context,
            'relation' => $detail,
            'contacts' => $contacts,
            'addresses' => $this->addresses->listForRelation($context->administration->id(), $relationId),
            'bankAccounts' => $this->bankAccounts->listForRelation($context->administration->id(), $relationId),
            'addressTypePresenter' => AddressTypePresenter::class,
            'canViewRelations' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::View->id()),
            'canUpdateRelations' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::Update->id()),
            'canClassifyCustomer' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::ClassifyCustomer->id()),
            'canClassifySupplier' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::ClassifySupplier->id()),
            'recipientPurposes' => SalesDocumentRecipientPurpose::cases(),
            'documentRecipients' => array_combine(array_map(static fn (SalesDocumentRecipientPurpose $purpose): string => $purpose->value, SalesDocumentRecipientPurpose::cases()), array_map(fn (SalesDocumentRecipientPurpose $purpose) => $this->documentRecipients->read($context->administration->id(), $relationId, $purpose), SalesDocumentRecipientPurpose::cases())),
        ]);
    }
}
