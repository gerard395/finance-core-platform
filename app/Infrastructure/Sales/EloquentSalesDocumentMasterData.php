<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\SalesDocumentIssuer;
use App\Application\Sales\SalesDocumentIssuerReader;
use App\Application\Sales\SalesDocumentMasterData;
use App\Application\Sales\SalesDocumentMasterDataStore;
use App\Application\Sales\SalesDocumentSender;
use App\Application\Sales\SalesDocumentSenderReader;
use App\Application\Sales\SalesDocumentSenderStatus;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentSalesDocumentMasterData implements SalesDocumentIssuerReader, SalesDocumentMasterDataStore, SalesDocumentSenderReader
{
    public function readIssuer(AdministrationId $administrationId): ?SalesDocumentIssuer
    {
        $record = AdministrationRecord::query()->find($administrationId->toString());
        if ($record === null) {
            return null;
        }

        return new SalesDocumentIssuer(
            $record->getAttribute('organisation_legal_name'),
            $record->getAttribute('organisation_display_name'),
            $this->addressLine($record->getAttribute('document_address_line_1')),
            $this->addressLine($record->getAttribute('document_address_line_2')),
            $this->postalCode($record->getAttribute('document_postal_code')),
            $this->city($record->getAttribute('document_city')),
            $this->country($record->getAttribute('document_country_code')),
            $record->getAttribute('organisation_vat_number') === null ? null : new VatIdentificationNumber($record->getAttribute('organisation_vat_number')),
            $this->country($record->getAttribute('fiscal_jurisdiction')),
            $record->getAttribute('organisation_chamber_of_commerce_number'),
            $this->email($record->getAttribute('document_business_email')),
            $record->getAttribute('document_business_phone'),
            $record->getAttribute('document_website'),
            $this->iban($record->getAttribute('organisation_iban')),
            $this->bic($record->getAttribute('organisation_bic')),
            $record->getAttribute('document_account_holder'),
        );
    }

    public function readSender(AdministrationId $administrationId): SalesDocumentSender
    {
        $record = AdministrationRecord::query()->find($administrationId->toString());
        $name = $record?->getAttribute('document_sender_name');
        $email = $record?->getAttribute('document_sender_email');
        $replyTo = $record?->getAttribute('document_reply_to_email');
        if ($name === null) {
            return new SalesDocumentSender(SalesDocumentSenderStatus::MissingFromName);
        }
        if ($email === null) {
            return new SalesDocumentSender(SalesDocumentSenderStatus::MissingFromEmail, $name);
        }
        try {
            $typedEmail = new EmailAddress($email);
        } catch (InvalidArgumentException) {
            return new SalesDocumentSender(SalesDocumentSenderStatus::InvalidFromEmail, $name);
        }
        try {
            $typedReplyTo = $replyTo === null ? null : new EmailAddress($replyTo);
        } catch (InvalidArgumentException) {
            return new SalesDocumentSender(SalesDocumentSenderStatus::InvalidReplyTo, $name, $typedEmail);
        }

        return new SalesDocumentSender(SalesDocumentSenderStatus::Success, $name, $typedEmail, $typedReplyTo);
    }

    public function update(AdministrationId $administrationId, SalesDocumentMasterData $data): bool
    {
        $query = AdministrationRecord::query()->whereKey($administrationId->toString());
        $currentOrganisationId = $query->value('organisation_id');
        $updated = $query->update([
            'organisation_id' => $data->displayName === null ? null : ($currentOrganisationId ?? Str::uuid()->toString()),
            'organisation_display_name' => $data->displayName,
            'organisation_legal_name' => $data->legalName,
            'organisation_chamber_of_commerce_number' => $data->registrationNumber,
            'document_address_line_1' => $data->addressLine1?->value(),
            'document_address_line_2' => $data->addressLine2?->value(),
            'document_postal_code' => $data->postalCode?->value(),
            'document_city' => $data->city?->value(),
            'document_country_code' => $data->countryCode?->value(),
            'document_business_email' => $data->businessEmail?->value(),
            'document_business_phone' => $data->businessPhone,
            'document_website' => $data->website,
            'organisation_iban' => $data->iban?->value(),
            'organisation_bic' => $data->bic?->value(),
            'document_account_holder' => $data->accountHolder,
            'document_sender_name' => $data->senderName,
            'document_sender_email' => $data->senderEmail?->value(),
            'document_reply_to_email' => $data->replyTo?->value(),
        ]);

        return $updated === 1 || $query->exists();
    }

    public function readMasterData(AdministrationId $administrationId): ?SalesDocumentMasterData
    {
        $record = AdministrationRecord::query()->find($administrationId->toString());
        if ($record === null) {
            return null;
        }

        return new SalesDocumentMasterData(
            $record->getAttribute('organisation_display_name'), $record->getAttribute('organisation_legal_name'),
            $record->getAttribute('organisation_chamber_of_commerce_number'), $this->addressLine($record->getAttribute('document_address_line_1')),
            $this->addressLine($record->getAttribute('document_address_line_2')), $this->postalCode($record->getAttribute('document_postal_code')),
            $this->city($record->getAttribute('document_city')), $this->country($record->getAttribute('document_country_code')),
            $this->email($record->getAttribute('document_business_email')), $record->getAttribute('document_business_phone'),
            $record->getAttribute('document_website'), $this->iban($record->getAttribute('organisation_iban')),
            $this->bic($record->getAttribute('organisation_bic')), $record->getAttribute('document_account_holder'),
            $record->getAttribute('document_sender_name'), $this->email($record->getAttribute('document_sender_email')),
            $this->email($record->getAttribute('document_reply_to_email')),
        );
    }

    private function addressLine(?string $value): ?AddressLine
    {
        return $value === null ? null : new AddressLine($value);
    }

    private function postalCode(?string $value): ?PostalCode
    {
        return $value === null ? null : new PostalCode($value);
    }

    private function city(?string $value): ?City
    {
        return $value === null ? null : new City($value);
    }

    private function country(?string $value): ?CountryCode
    {
        return $value === null ? null : new CountryCode($value);
    }

    private function email(?string $value): ?EmailAddress
    {
        return $value === null ? null : new EmailAddress($value);
    }

    private function iban(?string $value): ?Iban
    {
        return $value === null ? null : new Iban($value);
    }

    private function bic(?string $value): ?Bic
    {
        return $value === null ? null : new Bic($value);
    }
}
