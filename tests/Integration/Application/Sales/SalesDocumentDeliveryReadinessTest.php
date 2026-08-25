<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Sales;

use App\Application\Sales\ClearPreferredSalesDocumentRecipient;
use App\Application\Sales\SalesDocumentIssuerReader;
use App\Application\Sales\SalesDocumentIssuerReadiness;
use App\Application\Sales\SalesDocumentIssuerReadinessStatus;
use App\Application\Sales\SalesDocumentMasterData;
use App\Application\Sales\SalesDocumentRecipientPurpose;
use App\Application\Sales\SalesDocumentRecipientReader;
use App\Application\Sales\SalesDocumentRecipientStatus;
use App\Application\Sales\SalesDocumentSenderReader;
use App\Application\Sales\SalesDocumentSenderStatus;
use App\Application\Sales\SetPreferredSalesDocumentRecipient;
use App\Application\Sales\SetPreferredSalesDocumentRecipientResult;
use App\Application\Sales\UpdateSalesDocumentMasterData;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\Bic;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\ValueObjects\SalesDocumentRecipientPreferenceId;
use App\Domain\Shared\Identity\Uuid;
use App\Infrastructure\Persistence\Eloquent\Models\AdministrationRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationContactRecord;
use App\Infrastructure\Persistence\Eloquent\Models\RelationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

final class SalesDocumentDeliveryReadinessTest extends TestCase
{
    use RefreshDatabase;

    private const A = 'a1000000-0000-4000-8000-000000000001';

    private const B = 'b1000000-0000-4000-8000-000000000001';

    private const REL_A = 'a2000000-0000-4000-8000-000000000001';

    private const REL_B = 'b2000000-0000-4000-8000-000000000001';

    private const CONTACT_A = 'a3000000-0000-4000-8000-000000000001';

    private const CONTACT_B = 'b3000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant(self::A, self::REL_A, self::CONTACT_A, 'A');
        $this->tenant(self::B, self::REL_B, self::CONTACT_B, 'B');
    }

    public function test_each_purpose_is_explicit_readable_replaceable_and_clearable_without_fallback(): void
    {
        $set = $this->app->make(SetPreferredSalesDocumentRecipient::class);
        $reader = $this->app->make(SalesDocumentRecipientReader::class);
        foreach (SalesDocumentRecipientPurpose::cases() as $index => $purpose) {
            self::assertSame(SalesDocumentRecipientStatus::Missing, $reader->read($this->admin(self::A), $this->relation(self::REL_A), $purpose)->status);
            self::assertSame(SetPreferredSalesDocumentRecipientResult::Success, $set->execute($this->uuid($index + 1), $this->admin(self::A), $this->relation(self::REL_A), $purpose, $this->contact(self::CONTACT_A)));
            $recipient = $reader->read($this->admin(self::A), $this->relation(self::REL_A), $purpose);
            self::assertSame(SalesDocumentRecipientStatus::Success, $recipient->status);
            self::assertSame(self::CONTACT_A, $recipient->contactId?->toString());
            self::assertSame('Contact A', $recipient->displayName?->toString());
            self::assertSame('contact-a@example.com', $recipient->emailAddress?->toString());
        }
        self::assertSame(3, DB::table('sales_document_recipient_preferences')->count());
        $this->app->make(ClearPreferredSalesDocumentRecipient::class)->execute($this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation);
        self::assertSame(SalesDocumentRecipientStatus::Missing, $reader->read($this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation)->status);
        self::assertSame(2, DB::table('sales_document_recipient_preferences')->count());
    }

    public function test_wrong_relation_cross_tenant_inactive_and_no_email_are_safe_and_preferences_do_not_fallback(): void
    {
        $set = $this->app->make(SetPreferredSalesDocumentRecipient::class);
        self::assertSame(SetPreferredSalesDocumentRecipientResult::InvalidContact, $set->execute($this->uuid(10), $this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation, $this->contact(self::CONTACT_B)));
        RelationContactRecord::query()->whereKey(self::CONTACT_A)->update(['status' => 'inactive']);
        self::assertSame(SetPreferredSalesDocumentRecipientResult::InvalidContact, $set->execute($this->uuid(11), $this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation, $this->contact(self::CONTACT_A)));
        RelationContactRecord::query()->whereKey(self::CONTACT_A)->update(['status' => 'active', 'email' => null]);
        self::assertSame(SetPreferredSalesDocumentRecipientResult::InvalidContact, $set->execute($this->uuid(12), $this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation, $this->contact(self::CONTACT_A)));
        self::assertDatabaseCount('sales_document_recipient_preferences', 0);

        RelationContactRecord::query()->whereKey(self::CONTACT_A)->update(['email' => 'contact-a@example.com']);
        $set->execute($this->uuid(13), $this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation, $this->contact(self::CONTACT_A));
        RelationContactRecord::query()->whereKey(self::CONTACT_A)->update(['status' => 'inactive']);
        self::assertSame(SalesDocumentRecipientStatus::Invalid, $this->app->make(SalesDocumentRecipientReader::class)->read($this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation)->status);
        self::assertDatabaseCount('sales_document_recipient_preferences', 1);
    }

    public function test_document_master_data_roundtrips_tenant_safely_and_does_not_change_fiscal_truth(): void
    {
        $data = new SalesDocumentMasterData(
            'Trade A', 'Legal A B.V.', '12345678', new AddressLine('Main street 1'), null,
            new PostalCode('1234AB'), new City('Amsterdam'), new CountryCode('NL'), new EmailAddress('office@example.com'),
            '+31201234567', 'https://example.com', new Iban('NL91ABNA0417164300'), new Bic('ABNANL2A'),
            'Legal A B.V.', 'Finance A', new EmailAddress('finance@example.com'), new EmailAddress('reply@example.com'),
        );
        self::assertTrue($this->app->make(UpdateSalesDocumentMasterData::class)->execute($this->admin(self::A), $data));
        $issuer = $this->app->make(SalesDocumentIssuerReader::class)->readIssuer($this->admin(self::A));
        self::assertSame('Legal A B.V.', $issuer?->legalName);
        self::assertSame('Main street 1', $issuer?->addressLine1?->value());
        self::assertSame('NL91ABNA0417164300', $issuer?->iban?->value());
        self::assertSame('NL123456789B01', $issuer?->vatIdentificationNumber?->toString());
        self::assertNull($this->app->make(SalesDocumentIssuerReader::class)->readIssuer($this->admin('c1000000-0000-4000-8000-000000000001')));
        self::assertNull(AdministrationRecord::query()->find(self::B)?->getAttribute('organisation_legal_name'));

        $sender = $this->app->make(SalesDocumentSenderReader::class)->readSender($this->admin(self::A));
        self::assertSame(SalesDocumentSenderStatus::Success, $sender->status);
        self::assertSame('finance@example.com', $sender->fromEmail?->toString());
        self::assertSame(SalesDocumentIssuerReadinessStatus::Success, $this->app->make(SalesDocumentIssuerReadiness::class)->assess(SalesDocumentRecipientPurpose::SalesInvoice, $this->admin(self::A)));
    }

    public function test_legacy_nullable_sender_and_issuer_readiness_are_typed(): void
    {
        self::assertSame(SalesDocumentSenderStatus::MissingFromName, $this->app->make(SalesDocumentSenderReader::class)->readSender($this->admin(self::A))->status);
        self::assertSame(SalesDocumentIssuerReadinessStatus::MissingIssuerName, $this->app->make(SalesDocumentIssuerReadiness::class)->assess(SalesDocumentRecipientPurpose::Quotation, $this->admin(self::A)));
        AdministrationRecord::query()->whereKey(self::A)->update(['document_sender_name' => 'Finance', 'document_sender_email' => 'invalid']);
        self::assertSame(SalesDocumentSenderStatus::InvalidFromEmail, $this->app->make(SalesDocumentSenderReader::class)->readSender($this->admin(self::A))->status);
        AdministrationRecord::query()->whereKey(self::A)->update(['document_sender_email' => 'finance@example.com', 'document_reply_to_email' => 'invalid']);
        self::assertSame(SalesDocumentSenderStatus::InvalidReplyTo, $this->app->make(SalesDocumentSenderReader::class)->readSender($this->admin(self::A))->status);
    }

    public function test_real_mysql_concurrent_mutations_leave_exactly_one_preference(): void
    {
        if (! function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is required for the recipient concurrency test.');
        }
        $secondContact = 'a3000000-0000-4000-8000-000000000002';
        RelationContactRecord::query()->create(['contact_id' => $secondContact, 'administration_id' => self::A, 'relation_id' => self::REL_A, 'contact_name' => 'Contact A2', 'email' => 'contact-a2@example.com', 'phone' => null, 'status' => 'active']);
        DB::commit();
        $contacts = [self::CONTACT_A, $secondContact];
        $files = [tempnam(sys_get_temp_dir(), 'recipient-set-'), tempnam(sys_get_temp_dir(), 'recipient-set-')];
        $children = [];
        foreach ($files as $index => $file) {
            self::assertIsString($file);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $result = $this->app->make(SetPreferredSalesDocumentRecipient::class)->execute($this->uuid(30 + $index), $this->admin(self::A), $this->relation(self::REL_A), SalesDocumentRecipientPurpose::Quotation, $this->contact($contacts[$index]));
                    file_put_contents($file, $result->name);
                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($file, 'ERROR:'.$exception->getMessage());
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }
        self::assertSame(['Success', 'Success'], array_map(static fn (string $file): string => trim((string) file_get_contents($file)), $files));
        foreach ($files as $file) {
            unlink($file);
        }
        self::assertSame(1, DB::table('sales_document_recipient_preferences')->where('administration_id', self::A)->where('relation_id', self::REL_A)->where('purpose', 'quotation')->count());
        self::assertContains(DB::table('sales_document_recipient_preferences')->value('contact_id'), $contacts);
        DB::table('sales_document_recipient_preferences')->delete();
        DB::table('relation_contacts')->delete();
        DB::table('relations')->delete();
        DB::table('administrations')->delete();
        DB::beginTransaction();
    }

    private function tenant(string $administration, string $relation, string $contact, string $suffix): void
    {
        AdministrationRecord::query()->create(['id' => $administration, 'code' => 'DOC-'.$suffix, 'name' => 'Administration '.$suffix, 'base_currency' => 'EUR', 'status' => 'active', 'organisation_vat_number' => 'NL123456789B01', 'fiscal_jurisdiction' => 'NL']);
        RelationRecord::query()->create(['id' => $relation, 'administration_id' => $administration, 'code' => 'REL-'.$suffix, 'display_name' => 'Relation '.$suffix, 'active' => true]);
        RelationContactRecord::query()->create(['contact_id' => $contact, 'administration_id' => $administration, 'relation_id' => $relation, 'contact_name' => 'Contact '.$suffix, 'email' => 'contact-'.strtolower($suffix).'@example.com', 'phone' => null, 'status' => 'active']);
    }

    private function admin(string $id): AdministrationId
    {
        return new AdministrationId(new Uuid($id));
    }

    private function relation(string $id): RelationId
    {
        return new RelationId(new Uuid($id));
    }

    private function contact(string $id): ContactId
    {
        return new ContactId(new Uuid($id));
    }

    private function uuid(int $sequence): SalesDocumentRecipientPreferenceId
    {
        return new SalesDocumentRecipientPreferenceId(new Uuid(sprintf('c1000000-0000-4000-8000-%012d', $sequence)));
    }
}
