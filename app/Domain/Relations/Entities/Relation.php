<?php

declare(strict_types=1);

namespace App\Domain\Relations\Entities;

use App\Domain\Relations\ValueObjects\AddressId;
use App\Domain\Relations\ValueObjects\BankAccountId;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\DisplayName;
use App\Domain\Relations\ValueObjects\RelationCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Shared\Fiscal\VatIdentificationNumber;
use DomainException;

final class Relation
{
    /** @var array<string, Contact> */
    private array $contacts = [];

    /** @var array<string, Address> */
    private array $addresses = [];

    /** @var array<string, BankAccount> */
    private array $bankAccounts = [];

    public function __construct(
        private readonly RelationId $id,
        private readonly RelationCode $code,
        private DisplayName $displayName,
        private bool $active,
        private ?VatIdentificationNumber $vatIdentificationNumber = null,
        private ?CountryCode $fiscalJurisdiction = null,
    ) {}

    /**
     * @param  list<Contact>  $contacts
     * @param  list<Address>  $addresses
     * @param  list<BankAccount>  $bankAccounts
     */
    public static function reconstitute(
        RelationId $id,
        RelationCode $code,
        DisplayName $displayName,
        bool $active,
        array $contacts,
        array $addresses,
        array $bankAccounts,
        ?VatIdentificationNumber $vatIdentificationNumber = null,
        ?CountryCode $fiscalJurisdiction = null,
    ): self {
        $relation = new self($id, $code, $displayName, $active, $vatIdentificationNumber, $fiscalJurisdiction);
        $relation->contacts = self::indexContacts($contacts);
        $relation->addresses = self::indexAddresses($addresses);
        $relation->bankAccounts = self::indexBankAccounts($bankAccounts);

        return $relation;
    }

    public function id(): RelationId
    {
        return $this->id;
    }

    public function code(): RelationCode
    {
        return $this->code;
    }

    public function displayName(): DisplayName
    {
        return $this->displayName;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function vatIdentificationNumber(): ?VatIdentificationNumber
    {
        return $this->vatIdentificationNumber;
    }

    public function fiscalJurisdiction(): ?CountryCode
    {
        return $this->fiscalJurisdiction;
    }

    public function changeFiscalMasterData(?VatIdentificationNumber $vatIdentificationNumber, ?CountryCode $fiscalJurisdiction): void
    {
        $this->vatIdentificationNumber = $vatIdentificationNumber;
        $this->fiscalJurisdiction = $fiscalJurisdiction;
    }

    /** @return list<Contact> */
    public function contacts(): array
    {
        return array_values($this->contacts);
    }

    public function hasContact(ContactId $contactId): bool
    {
        return isset($this->contacts[$contactId->toString()]);
    }

    public function contact(ContactId $contactId): ?Contact
    {
        return $this->contacts[$contactId->toString()] ?? null;
    }

    public function addContact(Contact $contact): void
    {
        $key = $contact->id()->toString();

        if (isset($this->contacts[$key])) {
            throw new DomainException('Relation already contains a Contact with this identity.');
        }

        $this->contacts[$key] = $contact;
    }

    public function removeContact(ContactId $contactId): void
    {
        unset($this->contacts[$contactId->toString()]);
    }

    /** @return list<Address> */
    public function addresses(): array
    {
        return array_values($this->addresses);
    }

    public function hasAddress(AddressId $addressId): bool
    {
        return isset($this->addresses[$addressId->toString()]);
    }

    public function address(AddressId $addressId): ?Address
    {
        return $this->addresses[$addressId->toString()] ?? null;
    }

    public function addAddress(Address $address): void
    {
        $key = $address->id()->toString();

        if (isset($this->addresses[$key])) {
            throw new DomainException('Relation already contains an Address with this identity.');
        }

        $this->addresses[$key] = $address;
    }

    public function removeAddress(AddressId $addressId): void
    {
        unset($this->addresses[$addressId->toString()]);
    }

    /** @return list<BankAccount> */
    public function bankAccounts(): array
    {
        return array_values($this->bankAccounts);
    }

    public function hasBankAccount(BankAccountId $bankAccountId): bool
    {
        return isset($this->bankAccounts[$bankAccountId->toString()]);
    }

    public function bankAccount(BankAccountId $bankAccountId): ?BankAccount
    {
        return $this->bankAccounts[$bankAccountId->toString()] ?? null;
    }

    public function addBankAccount(BankAccount $bankAccount): void
    {
        $key = $bankAccount->id()->toString();

        if (isset($this->bankAccounts[$key])) {
            throw new DomainException('Relation already contains a BankAccount with this identity.');
        }

        $this->bankAccounts[$key] = $bankAccount;
    }

    public function removeBankAccount(BankAccountId $bankAccountId): void
    {
        unset($this->bankAccounts[$bankAccountId->toString()]);
    }

    public function rename(DisplayName $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * @param  list<Contact>  $contacts
     * @return array<string, Contact>
     */
    private static function indexContacts(array $contacts): array
    {
        $indexed = [];

        foreach ($contacts as $contact) {
            $key = $contact->id()->toString();

            if (isset($indexed[$key])) {
                throw new DomainException('Relation already contains a Contact with this identity.');
            }

            $indexed[$key] = $contact;
        }

        return $indexed;
    }

    /**
     * @param  list<Address>  $addresses
     * @return array<string, Address>
     */
    private static function indexAddresses(array $addresses): array
    {
        $indexed = [];

        foreach ($addresses as $address) {
            $key = $address->id()->toString();

            if (isset($indexed[$key])) {
                throw new DomainException('Relation already contains an Address with this identity.');
            }

            $indexed[$key] = $address;
        }

        return $indexed;
    }

    /**
     * @param  list<BankAccount>  $bankAccounts
     * @return array<string, BankAccount>
     */
    private static function indexBankAccounts(array $bankAccounts): array
    {
        $indexed = [];

        foreach ($bankAccounts as $bankAccount) {
            $key = $bankAccount->id()->toString();

            if (isset($indexed[$key])) {
                throw new DomainException('Relation already contains a BankAccount with this identity.');
            }

            $indexed[$key] = $bankAccount;
        }

        return $indexed;
    }
}
