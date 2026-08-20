<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('display_name');
            $table->string('email')->unique();
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_users');
    }
};
