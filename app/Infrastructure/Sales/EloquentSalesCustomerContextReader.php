<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesCustomerContext;
use App\Application\Sales\SalesCustomerContextReader;
use App\Application\Sales\SalesCustomerContextStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\Enums\AddressType;
use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\CustomerId;
use App\Domain\Relations\ValueObjects\CustomerNumber;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesAddressSnapshot;
use App\Domain\Sales\ValueObjects\SalesCustomerSnapshot;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\CustomerRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationAddressRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;

final class EloquentSalesCustomerContextReader implements SalesCustomerContextReader
{
    public function read(
        AdministrationId $administrationId,
        CustomerId $customerId,
        ?AddressId $invoiceAddressId,
    ): SalesCustomerContext {
        $customer = CustomerRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('id', $customerId->toString())
            ->first();

        if ($customer === null) {
            return SalesCustomerContext::failure(SalesCustomerContextStatus::NotFound);
        }
        if (! (bool) $customer->getAttribute('active')) {
            return SalesCustomerContext::failure(SalesCustomerContextStatus::InactiveCustomer);
        }

        $relationId = new RelationId(new Uuid($customer->getAttribute('relation_id')));
        $relation = RelationRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('id', $relationId->toString())
            ->first();
        if ($relation === null) {
            return SalesCustomerContext::failure(SalesCustomerContextStatus::NotFound);
        }

        $customerSnapshot = new SalesCustomerSnapshot(
            $customerId,
            $relationId,
            new CustomerNumber($customer->getAttribute('customer_number')),
            new DisplayName($relation->getAttribute('display_name')),
        );
        if ($invoiceAddressId === null) {
            return SalesCustomerContext::success($customerSnapshot, null);
        }

        $address = RelationAddressRecord::query()
            ->where('administration_id', $administrationId->toString())
            ->where('relation_id', $relationId->toString())
            ->where('address_id', $invoiceAddressId->toString())
            ->where('address_type', AddressType::Invoice->value)
            ->where('active', true)
            ->first();
        if ($address === null) {
            return SalesCustomerContext::failure(SalesCustomerContextStatus::MissingInvoiceAddress);
        }
        $line2 = $address->getAttribute('address_line_2');

        return SalesCustomerContext::success($customerSnapshot, new SalesAddressSnapshot(
            $invoiceAddressId,
            AddressType::Invoice,
            new AddressLine($address->getAttribute('address_line_1')),
            $line2 === null ? null : new AddressLine($line2),
            new PostalCode($address->getAttribute('postal_code')),
            new City($address->getAttribute('city')),
            new CountryCode($address->getAttribute('country_code')),
        ));
    }
}
