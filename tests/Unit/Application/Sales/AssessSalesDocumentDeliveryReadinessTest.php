<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales;

use App\Application\Sales\AssessSalesDocumentDeliveryReadiness;
use App\Application\Sales\DeliveryInfrastructureReadiness;
use App\Application\Sales\DeliveryInfrastructureReadinessStatus;
use App\Application\Sales\PrepareSalesDocumentArtifactStatus;
use App\Application\Sales\SalesDocumentDeliveryHistory;
use App\Application\Sales\SalesDocumentDeliveryHistoryReader;
use App\Application\Sales\SalesDocumentDeliveryInfrastructureReadiness;
use App\Application\Sales\SalesDocumentDeliveryReadinessStatus;
use App\Application\Sales\SalesDocumentDeliverySource;
use App\Application\Sales\SalesDocumentDeliverySourceReader;
use App\Application\Sales\SalesDocumentIssuer;
use App\Application\Sales\SalesDocumentIssuerReader;
use App\Application\Sales\SalesDocumentIssuerReadiness;
use App\Application\Sales\SalesDocumentReadinessChecker;
use App\Application\Sales\SalesDocumentRecipient;
use App\Application\Sales\SalesDocumentRecipientReader;
use App\Application\Sales\SalesDocumentRecipientStatus;
use App\Application\Sales\SalesDocumentSender;
use App\Application\Sales\SalesDocumentSenderReader;
use App\Application\Sales\SalesDocumentSenderStatus;
use App\Application\Sales\SalesDocumentSource;
use App\Domain\Administration\ValueObjects\AdministrationId;
use App\Domain\Relations\ValueObjects\AddressLine;
use App\Domain\Relations\ValueObjects\City;
use App\Domain\Relations\ValueObjects\ContactId;
use App\Domain\Relations\ValueObjects\ContactName;
use App\Domain\Relations\ValueObjects\CountryCode;
use App\Domain\Relations\ValueObjects\EmailAddress;
use App\Domain\Relations\ValueObjects\Iban;
use App\Domain\Relations\ValueObjects\PostalCode;
use App\Domain\Relations\ValueObjects\RelationId;
use App\Domain\Sales\Enums\SalesDocumentType;
use App\Domain\Sales\ValueObjects\QuotationId;
use App\Domain\Sales\ValueObjects\SalesCreditInvoiceId;
use App\Domain\Sales\ValueObjects\SalesInvoiceId;
use App\Domain\Shared\Identity\Uuid;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssessSalesDocumentDeliveryReadinessTest extends TestCase
{
    #[DataProvider('eligibilityCases')]
    public function test_document_status_eligibility_is_explicit(SalesDocumentType $type, string $status, bool $resend, SalesDocumentDeliveryReadinessStatus $expected): void
    {
        $history = $resend ? new SalesDocumentDeliveryHistory([['id' => 'request']], []) : new SalesDocumentDeliveryHistory([], []);
        self::assertSame($expected, $this->assessor($type, $status, $history)->execute($this->admin(), $this->source($type), $resend)->status);
    }

    public function test_unresolved_unknown_blocks_resend_but_authorize_resend_allows_it(): void
    {
        $attempt = ['id' => 'attempt', 'result' => 'outcome_unknown'];
        $unresolved = new SalesDocumentDeliveryHistory([['id' => 'request']], [$attempt]);
        self::assertSame(SalesDocumentDeliveryReadinessStatus::OutcomeUnknown, $this->assessor(SalesDocumentType::Quotation, 'draft', $unresolved)->execute($this->admin(), $this->source(SalesDocumentType::Quotation), true)->status);
        $authorized = new SalesDocumentDeliveryHistory([['id' => 'request']], [$attempt], ['attempt' => ['resolution_type' => 'authorize_resend']]);
        self::assertSame(SalesDocumentDeliveryReadinessStatus::Ready, $this->assessor(SalesDocumentType::Quotation, 'draft', $authorized)->execute($this->admin(), $this->source(SalesDocumentType::Quotation), true)->status);
    }

    public function test_invalid_document_state_is_not_reported_as_ready(): void
    {
        $history = new SalesDocumentDeliveryHistory([], []);

        self::assertSame(
            SalesDocumentDeliveryReadinessStatus::IneligibleStatus,
            $this->assessor(SalesDocumentType::Quotation, 'draft', $history, PrepareSalesDocumentArtifactStatus::InvalidSource)
                ->execute($this->admin(), $this->source(SalesDocumentType::Quotation), false)->status,
        );
    }

    public static function eligibilityCases(): array
    {
        return [
            'quotation draft initial' => [SalesDocumentType::Quotation, 'draft', false, SalesDocumentDeliveryReadinessStatus::Ready],
            'quotation sent resend' => [SalesDocumentType::Quotation, 'sent', true, SalesDocumentDeliveryReadinessStatus::Ready],
            'quotation accepted blocked' => [SalesDocumentType::Quotation, 'accepted', true, SalesDocumentDeliveryReadinessStatus::IneligibleStatus],
            'invoice finalized' => [SalesDocumentType::SalesInvoice, 'finalized', false, SalesDocumentDeliveryReadinessStatus::Ready],
            'invoice posted resend' => [SalesDocumentType::SalesInvoice, 'posted', true, SalesDocumentDeliveryReadinessStatus::Ready],
            'invoice paid' => [SalesDocumentType::SalesInvoice, 'paid', false, SalesDocumentDeliveryReadinessStatus::Ready],
            'invoice draft blocked' => [SalesDocumentType::SalesInvoice, 'draft', false, SalesDocumentDeliveryReadinessStatus::IneligibleStatus],
            'credit finalized' => [SalesDocumentType::SalesCreditInvoice, 'finalized', false, SalesDocumentDeliveryReadinessStatus::Ready],
            'credit posted resend' => [SalesDocumentType::SalesCreditInvoice, 'posted', true, SalesDocumentDeliveryReadinessStatus::Ready],
            'credit cancelled blocked' => [SalesDocumentType::SalesCreditInvoice, 'cancelled', false, SalesDocumentDeliveryReadinessStatus::IneligibleStatus],
        ];
    }

    private function assessor(
        SalesDocumentType $type,
        string $status,
        SalesDocumentDeliveryHistory $history,
        PrepareSalesDocumentArtifactStatus $documentStatus = PrepareSalesDocumentArtifactStatus::Success,
    ): AssessSalesDocumentDeliveryReadiness {
        $source = Mockery::mock(SalesDocumentDeliverySourceReader::class);
        $source->shouldReceive('read')->andReturn(new SalesDocumentDeliverySource($this->source($type), 'N000001', new RelationId(new Uuid('a1000000-0000-4000-8000-000000000002')), 'Customer', $status, true));
        $recipients = Mockery::mock(SalesDocumentRecipientReader::class);
        $recipients->shouldReceive('read')->andReturn(new SalesDocumentRecipient(SalesDocumentRecipientStatus::Success, new ContactId(new Uuid('a1000000-0000-4000-8000-000000000003')), new ContactName('Customer'), new EmailAddress('customer@example.test')));
        $issuerReader = Mockery::mock(SalesDocumentIssuerReader::class);
        $issuerReader->shouldReceive('readIssuer')->andReturn(new SalesDocumentIssuer(null, 'Issuer', new AddressLine('Street 1'), null, new PostalCode('1000AA'), new City('City'), new CountryCode('NL'), null, null, '12345678', null, null, null, new Iban('NL91ABNA0417164300'), null, 'Issuer'));
        $issuers = new SalesDocumentIssuerReadiness($issuerReader);
        $senders = Mockery::mock(SalesDocumentSenderReader::class);
        $senders->shouldReceive('readSender')->andReturn(new SalesDocumentSender(SalesDocumentSenderStatus::Success, 'Sender', new EmailAddress('sender@example.test')));
        $infrastructure = Mockery::mock(SalesDocumentDeliveryInfrastructureReadiness::class);
        $infrastructure->shouldReceive('check')->andReturn(new DeliveryInfrastructureReadiness(DeliveryInfrastructureReadinessStatus::Ready, 'database', 'sales-document-delivery', 1, []));
        $histories = Mockery::mock(SalesDocumentDeliveryHistoryReader::class);
        $histories->shouldReceive('history')->andReturn($history);

        $documents = Mockery::mock(SalesDocumentReadinessChecker::class);
        $documents->shouldReceive('check')->andReturn($documentStatus);

        return new AssessSalesDocumentDeliveryReadiness($source, $documents, $recipients, $issuers, $senders, $infrastructure, $histories);
    }

    private function source(SalesDocumentType $type): SalesDocumentSource
    {
        $uuid = new Uuid('a1000000-0000-4000-8000-000000000001');

        return match ($type) {
            SalesDocumentType::Quotation => SalesDocumentSource::quotation(new QuotationId($uuid)),
            SalesDocumentType::SalesInvoice => SalesDocumentSource::invoice(new SalesInvoiceId($uuid)),
            SalesDocumentType::SalesCreditInvoice => SalesDocumentSource::creditInvoice(new SalesCreditInvoiceId($uuid)),
        };
    }

    private function admin(): AdministrationId
    {
        return new AdministrationId(new Uuid('a1000000-0000-4000-8000-000000000004'));
    }
}
