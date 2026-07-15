<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relations\Entities;

use App\Domain\Relations\Entities\Contact;
use App\Domain\Relations\Enums\ContactStatus;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\PhoneNumber;
use App\Domain\Shared\Identity\Uuid;
use PHPUnit\Framework\TestCase;

final class ContactTest extends TestCase
{
    public function test_it_is_constructed_with_the_expected_state(): void
    {
        $contact = $this->createContact();

        self::assertSame('Zoë Jansen', $contact->name()->value());
        self::assertSame('zoe@example.com', $contact->emailAddress()?->value());
        self::assertSame('+31 20 1234567', $contact->phoneNumber()?->value());
        self::assertSame(ContactStatus::Active, $contact->status());
        self::assertTrue($contact->isActive());
    }

    public function test_it_can_be_renamed_without_changing_identity(): void
    {
        $contact = $this->createContact();
        $id = $contact->id();

        $contact->rename(new ContactName('Jan de Vries'));

        self::assertSame('Jan de Vries', $contact->name()->value());
        self::assertSame($id, $contact->id());
    }

    public function test_email_address_can_be_changed_and_removed(): void
    {
        $contact = $this->createContact();

        $contact->changeEmailAddress(new EmailAddress('Changed@Example.com'));
        self::assertSame('changed@example.com', $contact->emailAddress()?->value());

        $contact->changeEmailAddress(null);
        self::assertNull($contact->emailAddress());
    }

    public function test_phone_number_can_be_changed_and_removed(): void
    {
        $contact = $this->createContact();

        $contact->changePhoneNumber(new PhoneNumber('+31 10 7654321'));
        self::assertSame('+31 10 7654321', $contact->phoneNumber()?->value());

        $contact->changePhoneNumber(null);
        self::assertNull($contact->phoneNumber());
    }

    public function test_activate_and_deactivate_are_idempotent(): void
    {
        $contact = $this->createContact();

        $contact->deactivate();
        $contact->deactivate();
        self::assertSame(ContactStatus::Inactive, $contact->status());
        self::assertFalse($contact->isActive());

        $contact->activate();
        $contact->activate();
        self::assertSame(ContactStatus::Active, $contact->status());
        self::assertTrue($contact->isActive());
    }

    private function createContact(): Contact
    {
        return new Contact(
            new ContactId(new Uuid('550e8400-e29b-41d4-a716-446655440000')),
            new ContactName('Zoë Jansen'),
            new EmailAddress('Zoe@Example.com'),
            new PhoneNumber('+31 20 1234567'),
            ContactStatus::Active,
        );
    }
}
