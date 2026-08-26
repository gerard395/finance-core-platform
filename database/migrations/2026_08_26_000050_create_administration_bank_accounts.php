<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administration_bank_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('iban', 34);
            $table->string('bic', 11)->nullable();
            $table->string('account_holder');
            $table->string('label', 100);
            $table->char('currency', 3);
            $table->string('status');
            $table->timestamps();
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'iban']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_bank_accounts');
    }
};
