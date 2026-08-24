<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_number_sequences', function (Blueprint $table): void {
            $table->uuid('administration_id');
            $table->enum('sequence_type', ['quotation', 'order', 'sales_invoice', 'sales_credit_invoice']);
            $table->unsignedBigInteger('next_value');
            $table->boolean('active');
            $table->timestamps();

            $table->primary(['administration_id', 'sequence_type']);
            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE sales_number_sequences ADD CONSTRAINT sales_number_sequences_next_positive CHECK (next_value >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_number_sequences');
    }
};
