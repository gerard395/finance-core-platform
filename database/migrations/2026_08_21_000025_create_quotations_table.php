<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['administration_id', 'id'], 'customers_tenant_id_unique');
        });

        Schema::create('quotations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('quotation_number', 32);
            $table->uuid('customer_id');
            $table->uuid('customer_relation_id_snapshot');
            $table->string('customer_number_snapshot', 32);
            $table->string('customer_name_snapshot', 255);
            $table->char('currency', 3);
            $table->date('quotation_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired']);
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->foreign(['administration_id', 'customer_id'], 'quotations_customer_tenant_fk')
                ->references(['administration_id', 'id'])->on('customers')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'quotation_number']);
            $table->index(['administration_id', 'status', 'quotation_date', 'id']);
            $table->index(['administration_id', 'customer_id', 'quotation_date', 'id']);
        });

        Schema::create('quotation_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('quotation_id');
            $table->string('description', 1000);
            $table->string('quantity', 64);
            $table->text('unit_price_amount');
            $table->char('currency', 3);
            $table->timestamps();

            $table->foreign(['administration_id', 'quotation_id'], 'quotation_lines_quotation_tenant_fk')
                ->references(['administration_id', 'id'])->on('quotations')->restrictOnDelete();
            $table->unique(['administration_id', 'quotation_id', 'id'], 'quotation_lines_tenant_quotation_id_unique');
            $table->index(['administration_id', 'quotation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_tenant_id_unique');
        });
    }
};
