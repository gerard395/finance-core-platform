<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('code', 32);
            $table->string('display_name', 255);
            $table->boolean('active');
            $table->timestamps();

            $table->foreign('administration_id')->references('id')->on('administrations')->restrictOnDelete();
            $table->unique(['administration_id', 'id']);
            $table->unique(['administration_id', 'code']);
            $table->index(['administration_id', 'display_name', 'id']);
        });

        Schema::table('open_items', function (Blueprint $table): void {
            $table->foreign(['administration_id', 'relation_id'], 'oi_relation_tenant_fk')
                ->references(['administration_id', 'id'])->on('relations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('open_items', function (Blueprint $table): void {
            $table->dropForeign('oi_relation_tenant_fk');
        });

        Schema::dropIfExists('relations');
    }
};
