<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administration_membership_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('membership_id')->constrained('administration_memberships')->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('active');
            $table->timestamps();
            $table->unique(['membership_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_membership_roles');
    }
};
