<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retornos_bancarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('codigo_banco', 5);
            $table->string('nome_arquivo');
            $table->string('file_path')->nullable();
            $table->string('hash_sha256', 64);
            $table->string('status', 20)->default('pendente');
            $table->unsignedInteger('quantidade_ocorrencias')->default(0);
            $table->unsignedInteger('quantidade_liquidadas')->default(0);
            $table->unsignedInteger('quantidade_confirmadas')->default(0);
            $table->unsignedInteger('quantidade_excluidas')->default(0);
            $table->unsignedInteger('quantidade_rejeitadas')->default(0);
            $table->unsignedInteger('quantidade_ignoradas')->default(0);
            $table->unsignedInteger('quantidade_erros')->default(0);
            $table->text('erro')->nullable();
            $table->string('processado_por')->nullable();
            $table->string('processado_por_nome')->nullable();
            $table->timestamp('processamento_inicio')->nullable();
            $table->timestamp('processamento_termino')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'hash_sha256']);
            $table->index(['cliente_id', 'status']);
        });

        Schema::create('retorno_bancario_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('retorno_bancario_id')->constrained('retornos_bancarios')->cascadeOnDelete();
            $table->foreignUuid('cobranca_id')->nullable()->constrained('cobrancas')->nullOnDelete();
            $table->unsignedInteger('linha');
            $table->string('codigo_movimento', 2);
            $table->string('acao', 30);
            $table->string('status', 20)->default('pendente');
            $table->string('nosso_numero', 20)->nullable();
            $table->string('numero_registro', 20)->nullable();
            $table->date('vencimento')->nullable();
            $table->date('pago_em')->nullable();
            $table->decimal('valor_pago', 14, 2)->nullable();
            $table->string('motivo_rejeicao', 10)->nullable();
            $table->text('mensagem')->nullable();
            $table->text('linha_t')->nullable();
            $table->text('linha_u')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'retorno_bancario_id']);
            $table->index(['cliente_id', 'nosso_numero']);
            $table->index(['cobranca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retorno_bancario_itens');
        Schema::dropIfExists('retornos_bancarios');
    }
};
