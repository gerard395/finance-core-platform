<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administrations', function (Blueprint $table): void {
            $table->string('document_address_line_1')->nullable()->after('organisation_primary_address');
            $table->string('document_address_line_2')->nullable()->after('document_address_line_1');
            $table->string('document_postal_code', 16)->nullable()->after('document_address_line_2');
            $table->string('document_city')->nullable()->after('document_postal_code');
            $table->char('document_country_code', 2)->nullable()->after('document_city');
            $table->string('document_business_email')->nullable()->after('document_country_code');
            $table->string('document_business_phone', 32)->nullable()->after('document_business_email');
            $table->string('document_website')->nullable()->after('document_business_phone');
            $table->string('document_account_holder')->nullable()->after('organisation_bic');
            $table->string('document_sender_name')->nullable()->after('document_account_holder');
            $table->string('document_sender_email')->nullable()->after('document_sender_name');
            $table->string('document_reply_to_email')->nullable()->after('document_sender_email');
        });

        Schema::table('relation_contacts', function (Blueprint $table): void {
            $table->unique(['administration_id', 'relation_id', 'contact_id'], 'contacts_tenant_relation_id_unique');
        });

        Schema::create('sales_document_recipient_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('relation_id');
            $table->enum('purpose', ['quotation', 'sales_invoice', 'sales_credit_invoice']);
            $table->uuid('contact_id');
            $table->timestamps();

            $table->foreign(['administration_id', 'relation_id'], 'recipient_preference_relation_tenant_fk')
                ->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $table->foreign(['administration_id', 'relation_id', 'contact_id'], 'recipient_preference_contact_tenant_fk')
                ->references(['administration_id', 'relation_id', 'contact_id'])->on('relation_contacts')->restrictOnDelete();
            $table->unique(['administration_id', 'relation_id', 'purpose'], 'recipient_preference_tenant_relation_purpose_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_document_recipient_preferences');
        Schema::table('relation_contacts', function (Blueprint $table): void {
            $table->dropUnique('contacts_tenant_relation_id_unique');
        });
        Schema::table('administrations', function (Blueprint $table): void {
            $table->dropColumn([
                'document_address_line_1', 'document_address_line_2', 'document_postal_code', 'document_city',
                'document_country_code', 'document_business_email', 'document_business_phone', 'document_website',
                'document_account_holder', 'document_sender_name', 'document_sender_email', 'document_reply_to_email',
            ]);
        });
    }
};
