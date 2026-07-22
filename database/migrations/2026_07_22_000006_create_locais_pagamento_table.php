<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locais_pagamento', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('descricao', 80);
            $table->boolean('cartao_credito')->default(false);
            $table->decimal('taxa_cartao', 8, 4)->nullable();
            $table->unsignedSmallInteger('qtd_dias_credito')->nullable();
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(100);
            $table->timestamps();

            $table->unique(['cliente_id', 'codigo']);
            $table->index(['cliente_id', 'ativo', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locais_pagamento');
    }
};
