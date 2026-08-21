<?php

declare(strict_types=1);

namespace App\Http\Controllers\Relations;

use App\Application\Identity\PermissionAuthorizer;
use App\Application\Relations\ActivateAddress;
use App\Application\Relations\AddressDetail;
use App\Application\Relations\AddressReadRepository;
use App\Application\Relations\AddressWriteResult;
use App\Application\Relations\CreateAddress;
use App\Application\Relations\DeactivateAddress;
use App\Application\Relations\GetRelationDetail;
use App\Application\Relations\RelationDetail;
use App\Application\Relations\UpdateAddress;
use App\Domain\Identity\Definitions\RelationsPermission;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Identity\Uuid;
use App\Http\Administration\ActiveAdministrationContext;
use App\Http\Requests\Relations\StoreAddressRequest;
use App\Http\Requests\Relations\UpdateAddressRequest;
use App\Presentation\Relations\AddressTypePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

final readonly class AddressController
{
    public function __construct(private GetRelationDetail $relations, private AddressReadRepository $addresses, private CreateAddress $createAddress, private UpdateAddress $updateAddress, private ActivateAddress $activateAddress, private DeactivateAddress $deactivateAddress, private PermissionAuthorizer $permissionAuthorizer) {}

    public function show(Request $request, string $relation, string $address): View
    {
        [$context, $relationId, $relationDetail] = $this->relationContext($request, $relation);

        return view('relations.addresses.show', $this->viewData($context, $relationDetail, $this->addressDetail($context, $relationId, $address)));
    }

    public function create(Request $request, string $relation): View
    {
        [$context, , $detail] = $this->relationContext($request, $relation);

        return view('relations.addresses.create', $this->viewData($context, $detail));
    }

    public function store(StoreAddressRequest $request, string $relation): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);
        $data = $request->validated();
        try {
            $result = $this->createAddress->execute($context->administration->id(), $relationId, new AddressId(new Uuid(Str::uuid()->toString())), AddressType::from($data['type']), new AddressLine($data['address_line_1']), isset($data['address_line_2']) ? new AddressLine($data['address_line_2']) : null, new PostalCode($data['postal_code']), new City($data['city']), new CountryCode($data['country_code']));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['address_line_1' => 'De adresgegevens zijn ongeldig.']);
        }

        return $this->response($context, $relationId, $result, 'Adres toegevoegd.');
    }

    public function edit(Request $request, string $relation, string $address): View
    {
        [$context, $relationId, $detail] = $this->relationContext($request, $relation);

        return view('relations.addresses.edit', $this->viewData($context, $detail, $this->addressDetail($context, $relationId, $address)));
    }

    public function update(UpdateAddressRequest $request, string $relation, string $address): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);
        $data = $request->validated();
        try {
            $result = $this->updateAddress->execute($context->administration->id(), $relationId, $this->addressId($address), new AddressLine($data['address_line_1']), isset($data['address_line_2']) ? new AddressLine($data['address_line_2']) : null, new PostalCode($data['postal_code']), new City($data['city']), new CountryCode($data['country_code']));
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['address_line_1' => 'De adresgegevens zijn ongeldig.']);
        }

        return $this->response($context, $relationId, $result, 'Adres bijgewerkt.');
    }

    public function activate(Request $request, string $relation, string $address): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);

        return $this->response($context, $relationId, $this->activateAddress->execute($context->administration->id(), $relationId, $this->addressId($address)), 'Adres geactiveerd.');
    }

    public function deactivate(Request $request, string $relation, string $address): RedirectResponse
    {
        [$context, $relationId] = $this->relationContext($request, $relation);

        return $this->response($context, $relationId, $this->deactivateAddress->execute($context->administration->id(), $relationId, $this->addressId($address)), 'Adres gedeactiveerd.');
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

    private function addressDetail(ActiveAdministrationContext $context, RelationId $relationId, string $address): AddressDetail
    {
        $detail = $this->addresses->findForRelation($context->administration->id(), $relationId, $this->addressId($address));
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

    private function addressId(string $value): AddressId
    {
        try {
            return new AddressId(new Uuid($value));
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function response(ActiveAdministrationContext $context, RelationId $relationId, AddressWriteResult $result, string $message): RedirectResponse
    {
        abort_if($result === AddressWriteResult::NotFound, 404);
        if ($result !== AddressWriteResult::Success) {
            return back()->withInput()->withErrors(['address_line_1' => 'Het adres kon niet worden opgeslagen. Probeer het opnieuw.']);
        }

        return $this->canView($context) ? redirect()->route('relations.show', $relationId->toString())->with('status', $message) : redirect()->route('app')->with('status', $message);
    }

    /** @return array<string, mixed> */
    private function viewData(ActiveAdministrationContext $context, RelationDetail $relation, ?AddressDetail $address = null): array
    {
        return ['domainUser' => $context->user, 'administrationContext' => $context, 'relation' => $relation, 'address' => $address, 'addressTypes' => AddressType::cases(), 'typePresenter' => AddressTypePresenter::class, 'canViewRelations' => $this->canView($context), 'canUpdateRelations' => $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::Update->id())];
    }

    private function canView(ActiveAdministrationContext $context): bool
    {
        return $this->permissionAuthorizer->allows($context->permissionIds, RelationsPermission::View->id());
    }
}
