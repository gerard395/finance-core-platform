<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relation_addresses', function (Blueprint $table): void {
            $table->uuid('address_id')->primary();
            $table->uuid('administration_id');
            $table->uuid('relation_id');
            $table->string('address_type', 16);
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('postal_code', 16);
            $table->string('city', 255);
            $table->char('country_code', 2);
            $table->boolean('active');
            $table->timestamps();
            $table->foreign(['administration_id', 'relation_id'], 'address_relation_tenant_fk')->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $table->index(['administration_id', 'relation_id', 'address_type', 'address_id'], 'address_relation_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relation_addresses');
    }
};
