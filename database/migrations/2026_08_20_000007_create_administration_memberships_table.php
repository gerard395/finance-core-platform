<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administration_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('domain_users')->restrictOnDelete();
            $table->foreignUuid('administration_id')->constrained('administrations')->restrictOnDelete();
            $table->boolean('active');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->timestamps();
            $table->unique(['user_id', 'administration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_memberships');
    }
};
