<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('description', 1000)->nullable();
            $table->char('base_currency', 3);
            $table->string('status');
            $table->uuid('organisation_id')->nullable()->unique();
            $table->string('organisation_display_name')->nullable();
            $table->string('organisation_legal_name')->nullable();
            $table->string('organisation_legal_form')->nullable();
            $table->string('organisation_chamber_of_commerce_number')->nullable();
            $table->string('organisation_vat_number')->nullable();
            $table->string('organisation_primary_address')->nullable();
            $table->string('organisation_iban')->nullable();
            $table->string('organisation_bic')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrations');
    }
};
