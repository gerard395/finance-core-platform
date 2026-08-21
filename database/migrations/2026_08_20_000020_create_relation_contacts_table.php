<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relation_contacts', function (Blueprint $table): void {
            $table->uuid('contact_id')->primary();
            $table->uuid('administration_id');
            $table->uuid('relation_id');
            $table->string('contact_name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 16);
            $table->timestamps();

            $table->foreign(['administration_id', 'relation_id'], 'contact_relation_tenant_fk')
                ->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
            $table->index(['administration_id', 'relation_id', 'contact_name', 'contact_id'], 'contact_relation_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relation_contacts');
    }
};
