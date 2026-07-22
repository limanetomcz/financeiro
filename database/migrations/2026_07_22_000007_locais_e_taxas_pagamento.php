<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa canal (local) de condição/tarifa (taxa).
 * Snapshot na cobrança para histórico de baixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catálogo antigo do seed genérico (01–99) não serve mais.
        DB::table('locais_pagamento')->delete();

        Schema::table('locais_pagamento', function (Blueprint $table) {
            $table->string('tipo', 20)->default('banco')->after('descricao');
            $table->string('codigo_legado', 10)->nullable()->after('codigo');
            $table->dropColumn(['cartao_credito', 'taxa_cartao', 'qtd_dias_credito']);
        });

        Schema::create('taxas_local_pagamento', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignUuid('local_pagamento_id')->constrained('locais_pagamento')->cascadeOnDelete();
            $table->string('codigo_legado', 10)->nullable();
            $table->string('descricao', 80);
            $table->string('modalidade', 30);
            $table->string('bandeira', 30)->default('qualquer');
            $table->decimal('taxa_percentual', 8, 4)->default(0);
            $table->unsignedSmallInteger('dias_credito')->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(100);
            $table->timestamps();

            $table->unique(['cliente_id', 'codigo_legado']);
            $table->index(['cliente_id', 'local_pagamento_id', 'ativo']);
        });

        Schema::table('cobrancas', function (Blueprint $table) {
            $table->foreignUuid('local_pagamento_id')->nullable()->after('meio');
            $table->foreignUuid('taxa_local_pagamento_id')->nullable()->after('local_pagamento_id');
            $table->decimal('taxa_percentual', 8, 4)->nullable()->after('local_pagamento_descricao');
            $table->decimal('valor_taxa', 12, 2)->nullable()->after('taxa_percentual');
            $table->string('modalidade', 30)->nullable()->after('valor_taxa');
            $table->string('bandeira', 30)->nullable()->after('modalidade');
        });

        // FKs após colunas (MySQL)
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->foreign('local_pagamento_id')->references('id')->on('locais_pagamento')->nullOnDelete();
            $table->foreign('taxa_local_pagamento_id')->references('id')->on('taxas_local_pagamento')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropForeign(['local_pagamento_id']);
            $table->dropForeign(['taxa_local_pagamento_id']);
            $table->dropColumn([
                'local_pagamento_id',
                'taxa_local_pagamento_id',
                'taxa_percentual',
                'valor_taxa',
                'modalidade',
                'bandeira',
            ]);
        });

        Schema::dropIfExists('taxas_local_pagamento');

        Schema::table('locais_pagamento', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'codigo_legado']);
            $table->boolean('cartao_credito')->default(false);
            $table->decimal('taxa_cartao', 8, 4)->nullable();
            $table->unsignedSmallInteger('qtd_dias_credito')->nullable();
        });
    }
};
