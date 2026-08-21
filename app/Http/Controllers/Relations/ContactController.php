<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\ActivateContact;
use App\Application\Relations\ContactDetail;
use App\Application\Relations\ContactReadRepository;
use App\Application\Relations\ContactWriteResult;
use App\Application\Relations\CreateContact;
use App\Application\Relations\DeactivateContact;
use App\Application\Relations\GetRelationDetail;
use App\Application\Relations\RelationDetail;
use App\Application\Relations\UpdateContact;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Relations\StoreContactRequest;
use App\Http\Requests\Relations\UpdateContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class ContactController
{
    public function __construct(
        private GetRelationDetail $relations,
        private ContactReadRepository $contacts,
        private CreateContact $createContact,
        private UpdateContact $updateContact,
        private ActivateContact $activateContact,
        private DeactivateContact $deactivateContact,
        private PermissionAuthorizer $permissionAuthorizer,
    ) {}

    public function show(Request $request, string $relation, string $contact): View
    {
        [$context, $relationId, $relationDetail] = $this->relationContext($request, $relation);
        $contactDetail = $this->contactDetail($context, $relationId, $contact);

        return view('relations.contacts.show', $this->viewData($context, $relationDetail, $contactDetail));
    }

    public function create(Request $request, string $relation): View
    {
        [$context, , $relationDetail] = $this->relationContext($request, $relation);

        return view('relations.contacts.create', $this->viewData($context, $relationDetail));
    }

    public function store(StoreContactRequest $request, string $relation): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);
        $validated = $request->validated();

        try {
            $result = $this->createContact->execute(
                $context->administration->id(), $relationId, new ContactId(new Uuid(Str::uuid()->toString())),
                new ContactName($validated['name']),
                isset($validated['email']) ? new EmailAddress($validated['email']) : null,
                isset($validated['phone']) ? new PhoneNumber($validated['phone']) : null,
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['name' => 'De contactgegevens zijn ongeldig.']);
        }

        return $this->mutationResponse($context, $relationId, $result, 'Contactpersoon toegevoegd.');
    }

    public function edit(Request $request, string $relation, string $contact): View
    {
        [$context, $relationId, $relationDetail] = $this->relationContext($request, $relation);

        return view('relations.contacts.edit', $this->viewData($context, $relationDetail, $this->contactDetail($context, $relationId, $contact)));
    }

    public function update(UpdateContactRequest $request, string $relation, string $contact): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);
        $validated = $request->validated();

        try {
            $result = $this->updateContact->execute(
                $context->administration->id(), $relationId, $this->contactId($contact),
                new ContactName($validated['name']),
                isset($validated['email']) ? new EmailAddress($validated['email']) : null,
                isset($validated['phone']) ? new PhoneNumber($validated['phone']) : null,
            );
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['name' => 'De contactgegevens zijn ongeldig.']);
        }

        return $this->mutationResponse($context, $relationId, $result, 'Contactpersoon bijgewerkt.');
    }

    public function activate(Request $request, string $relation, string $contact): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);

        return $this->mutationResponse($context, $relationId, $this->activateContact->execute($context->administration->id(), $relationId, $this->contactId($contact)), 'Contactpersoon geactiveerd.');
    }

    public function deactivate(Request $request, string $relation, string $contact): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);

        return $this->mutationResponse($context, $relationId, $this->deactivateContact->execute($context->administration->id(), $relationId, $this->contactId($contact)), 'Contactpersoon gedeactiveerd.');
    }

    /** @return array{ActiveAdministrationContext, RelationId, RelationDetail} */
    private function relationContext(Request $request, string $relation): array
    {
        /** @var ActiveAdministrationContext $context */
        $context = $request->attributes->get('administration_context');
        $relationId = $this->relationId($relation);
        $detail = $this->relations->execute($context->administration->id(), $relationId);
        abort_if($detail === null, 404);

        return [$context, $relationId, $detail];
    }

    private function contactDetail(ActiveAdministrationContext $context, RelationId $relationId, string $contact): ContactDetail
    {
        $detail = $this->contacts->findForRelation($context->administration->id(), $relationId, $this->contactId($contact));
        abort_if($detail === null, 404);

        return $detail;
    }

    private function relationId(string $value): RelationId
    {
        try {
            return new RelationId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function contactId(string $value): ContactId
    {
        try {
            return new ContactId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function mutationResponse(ActiveAdministrationContext $context, RelationId $relationId, ContactWriteResult $result, string $message): RedirectResponse
    {
        abort_if($result === ContactWriteResult::NotFound, 404);
        if ($result !== ContactWriteResult::Success) {
            return back()->withInput()->withErrors(['name' => 'De contactpersoon kon niet worden opgeslagen. Probeer het opnieuw.']);
        }

        return $this->canView($context)
            ? redirect()->route('relations.show', $relationId->toString())->with('status', $message)
            : redirect()->route('app')->with('status', $message);
    }

    /** @return array<string, mixed> */
    private function viewData(ActiveAdministrationContext $context, RelationDetail $relation, ?ContactDetail $contact = null): array
    {
        return [
            'domainUser' => $context->user, 'administrationContext' => $context, 'relation' => $relation, 'contact' => $contact,
            'canViewRelations' => $this->canView($context),
            'canUpdateRelations' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::Update->id()),
        ];
    }

    private function canView(ActiveAdministrationContext $context): bool
    {
        return $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::View->id());
    }
}
