<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratantes', function (Blueprint $table) {
            $table->uuid('empresa_id')->nullable()->after('cliente_id');
            $table->foreign('empresa_id')->references('id')->on('contratantes')->nullOnDelete();
            $table->index(['cliente_id', 'empresa_id']);
        });

        Schema::create('faturas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('contratante_id')->constrained('contratantes')->cascadeOnDelete();
            $table->string('competencia', 7); // YYYY-MM
            $table->date('vencimento');
            $table->decimal('valor_bruto', 12, 2)->default(0);
            $table->decimal('valor_retencoes', 12, 2)->default(0);
            $table->decimal('valor_acrescimos', 12, 2)->default(0);
            $table->decimal('valor_liquido', 12, 2)->default(0);
            $table->string('status', 20)->default('rascunho');
            $table->uuid('cobranca_id')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'contratante_id', 'competencia']);
            $table->index(['cliente_id', 'status', 'vencimento']);
            $table->foreign('cobranca_id')->references('id')->on('cobrancas')->nullOnDelete();
        });

        Schema::create('fatura_lancamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->string('codigo', 40);
            $table->string('descricao');
            $table->string('natureza', 20); // base|retencao|acrescimo|informativo
            $table->string('origem', 30); // soma_parcelas|manual|formula
            $table->decimal('valor', 12, 2);
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['fatura_id', 'ordem']);
        });

        Schema::create('fatura_parcela', function (Blueprint $table) {
            $table->foreignUuid('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->foreignUuid('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->primary(['fatura_id', 'parcela_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fatura_parcela');
        Schema::dropIfExists('fatura_lancamentos');
        Schema::dropIfExists('faturas');

        Schema::table('contratantes', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropColumn('empresa_id');
        });
    }
};
