<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('chave_sigoweb', 64);
            $table->string('tipo', 10); // pf|pj
            $table->string('nome');
            $table->string('documento', 20)->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'chave_sigoweb']);
            $table->index(['cliente_id', 'documento']);
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('contratante_id')->constrained('contratantes')->cascadeOnDelete();
            $table->uuid('renovado_de_contrato_id')->nullable();
            $table->string('chave_plano_sigoweb', 64)->nullable();
            $table->string('codigo', 40)->nullable();
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim');
            $table->decimal('valor_total', 12, 2);
            $table->unsignedSmallInteger('quantidade_parcelas');
            $table->string('status', 20)->default('ativo');
            $table->timestamps();

            $table->foreign('renovado_de_contrato_id')->references('id')->on('contratos')->nullOnDelete();
            $table->index(['cliente_id', 'status']);
            $table->index(['cliente_id', 'contratante_id']);
        });

        Schema::create('parcelas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('vencimento');
            $table->decimal('valor', 12, 2);
            $table->string('status', 20)->default('aberta');
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();

            $table->unique(['contrato_id', 'numero']);
            $table->index(['cliente_id', 'status', 'vencimento']);
        });

        Schema::create('cobrancas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('contratante_id')->constrained('contratantes')->cascadeOnDelete();
            $table->string('tipo', 20); // simples|consolidada
            $table->decimal('valor', 12, 2);
            $table->date('vencimento');
            $table->string('status', 20)->default('aberta');
            $table->string('meio', 20)->nullable(); // boleto|pix|manual|outro
            $table->string('referencia_externa', 80)->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'status']);
            $table->index(['cliente_id', 'contratante_id']);
        });

        Schema::create('cobranca_parcela', function (Blueprint $table) {
            $table->foreignUuid('cobranca_id')->constrained('cobrancas')->cascadeOnDelete();
            $table->foreignUuid('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->decimal('valor_alocado', 12, 2);
            $table->primary(['cobranca_id', 'parcela_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_parcela');
        Schema::dropIfExists('cobrancas');
        Schema::dropIfExists('parcelas');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('contratantes');
    }
};
