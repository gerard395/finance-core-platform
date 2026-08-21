<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('code', 16);
            $table->string('name');
            $table->string('rate', 8);
            $table->enum('direction', ['input', 'output']);
            $table->enum('status', ['active', 'inactive']);
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'code']);
            $table->index(['administration_id', 'direction', 'status', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
