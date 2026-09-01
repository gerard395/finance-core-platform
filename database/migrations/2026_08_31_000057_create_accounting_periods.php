<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_years', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('administration_id');
            $t->string('code', 50);
            $t->string('label', 100)->default('');
            $t->date('start_date');
            $t->date('end_date');
            $t->timestamps();
            $t->unique(['administration_id', 'id']);
            $t->unique(['administration_id', 'code']);
            $t->index(['administration_id', 'start_date', 'end_date']);
            $t->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
        });
        Schema::create('accounting_periods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('administration_id');
            $t->uuid('book_year_id');
            $t->string('code', 50);
            $t->string('label', 100);
            $t->date('start_date');
            $t->date('end_date');
            $t->string('status', 16);
            $t->timestamps();
            $t->unique(['administration_id', 'id']);
            $t->unique(['administration_id', 'book_year_id', 'id']);
            $t->unique(['administration_id', 'book_year_id', 'code']);
            $t->index(['administration_id', 'start_date', 'end_date', 'status'], 'ap_date_status_idx');
            $t->foreign(['administration_id', 'book_year_id'])->references(['administration_id', 'id'])->on('book_years')->restrictOnDelete();
        });
        Schema::create('accounting_period_status_history', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('administration_id');
            $t->uuid('book_year_id');
            $t->uuid('accounting_period_id');
            $t->string('from_status', 16);
            $t->string('to_status', 16);
            $t->string('reason', 500);
            $t->uuid('actor_id');
            $t->timestamp('occurred_at');
            $t->unique(['administration_id', 'id']);
            $t->index(['administration_id', 'accounting_period_id', 'occurred_at'], 'apsh_period_time_idx');
            $t->foreign(['administration_id', 'book_year_id', 'accounting_period_id'], 'apsh_period_fk')->references(['administration_id', 'book_year_id', 'id'])->on('accounting_periods')->restrictOnDelete();
            $t->foreign('actor_id')->references('id')->on('domain_users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_status_history');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('book_years');
    }
};
