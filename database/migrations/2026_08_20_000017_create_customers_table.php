<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->uuid('relation_id');
            $table->string('customer_number', 32);
            $table->boolean('active');
            $table->timestamps();

            $table->foreign(['administration_id', 'relation_id'], 'customer_relation_tenant_fk')
                ->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $table->unique(['administration_id', 'relation_id']);
            $table->unique(['administration_id', 'customer_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
