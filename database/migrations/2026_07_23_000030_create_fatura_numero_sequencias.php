<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fatura_numero_sequencias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('referencia', 6); // AAAAMM
            $table->unsignedInteger('ultimo')->default(0);
            $table->timestamps();

            $table->unique(['cliente_id', 'referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_numero_sequencias');
    }
};
