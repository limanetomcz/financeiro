<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->string('nosso_numero', 20)->nullable()->after('referencia_externa');
            $table->string('numero_registro', 20)->nullable()->after('nosso_numero');
            $table->date('data_emissao_boleto')->nullable()->after('numero_registro');
            $table->index(['cliente_id', 'nosso_numero']);
        });

        Schema::create('remessas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedInteger('lote');
            $table->string('codigo_banco', 5);
            $table->string('status', 20)->default('pendente');
            $table->date('vencimento_inicial');
            $table->date('vencimento_final');
            $table->unsignedInteger('quantidade_titulos')->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('geracao_inicio')->nullable();
            $table->timestamp('geracao_termino')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'lote']);
            $table->index(['cliente_id', 'status']);
        });

        Schema::create('remessa_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('remessa_id')->constrained('remessas')->cascadeOnDelete();
            $table->foreignUuid('cobranca_id')->nullable()->constrained('cobrancas')->nullOnDelete();
            $table->string('nosso_numero', 20);
            $table->string('numero_registro', 20)->nullable();
            $table->string('operacao', 2)->default('01');
            $table->string('tipo_boleto', 5)->default('C');
            $table->decimal('valor', 12, 2);
            $table->decimal('valor_juros_dia', 12, 4)->default(0);
            $table->decimal('valor_multa', 12, 2)->default(0);
            $table->date('vencimento');
            $table->date('data_emissao');
            $table->unsignedSmallInteger('dias_devolucao')->default(60);
            $table->string('codigo_multa', 1)->default('1');
            $table->unsignedTinyInteger('enviado_remessa')->default(0);
            $table->json('pagador');
            $table->timestamps();

            $table->index(['cliente_id', 'remessa_id']);
            $table->index(['cliente_id', 'nosso_numero']);
            $table->index(['cobranca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remessa_itens');
        Schema::dropIfExists('remessas');

        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropIndex(['cliente_id', 'nosso_numero']);
            $table->dropColumn(['nosso_numero', 'numero_registro', 'data_emissao_boleto']);
        });
    }
};
