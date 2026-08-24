<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('code', 16);
            $table->string('name');
            $table->enum('type', ['sales', 'purchase', 'bank', 'cash', 'general']);
            $table->enum('status', ['active', 'inactive']);
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'code']);
            $table->index(['administration_id', 'type', 'status', 'code'], 'journals_tenant_type_status_code_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('journal_entries') && DB::table('journal_entries')->exists()) {
            throw new RuntimeException('Cannot remove Journal masterdata while financial history exists.');
        }

        Schema::dropIfExists('journals');
    }
};
