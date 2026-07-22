<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composição do contrato por integrante da família (DIRF / detalhe ao cliente).
 * valor_total do contrato = soma(valor_mensal) × quantidade_parcelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_beneficiarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('chave_sigoweb', 64);
            $table->string('chave_depend_sigoweb', 10)->nullable();
            $table->string('nome', 255);
            $table->string('documento', 20)->nullable();
            $table->string('tipo_dependencia', 20)->default('dependente'); // titular|dependente
            $table->string('tipodep_sigoweb', 10)->nullable(); // ben_tipodep legado
            $table->decimal('valor_mensal', 12, 2);
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->timestamps();

            $table->unique(['contrato_id', 'chave_sigoweb']);
            $table->index(['cliente_id', 'chave_sigoweb']);
        });

        Schema::create('parcela_beneficiarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('parcela_id')->constrained('parcelas')->cascadeOnDelete();
            $table->foreignUuid('contrato_beneficiario_id')->nullable()
                ->constrained('contrato_beneficiarios')->nullOnDelete();
            $table->string('chave_sigoweb', 64);
            $table->string('nome', 255);
            $table->string('documento', 20)->nullable();
            $table->string('tipo_dependencia', 20)->default('dependente');
            $table->decimal('valor', 12, 2);
            $table->timestamps();

            $table->unique(['parcela_id', 'chave_sigoweb']);
            $table->index(['cliente_id', 'chave_sigoweb']);
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->string('chave_familia_sigoweb', 20)->nullable()->after('chave_plano_sigoweb');
            $table->decimal('valor_mensal_familia', 12, 2)->nullable()->after('valor_total');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['chave_familia_sigoweb', 'valor_mensal_familia']);
        });
        Schema::dropIfExists('parcela_beneficiarios');
        Schema::dropIfExists('contrato_beneficiarios');
    }
};
