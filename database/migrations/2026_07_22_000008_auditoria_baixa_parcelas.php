<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria de quem baixou / retirou baixa.
 * Fusca virá depois — por enquanto só colunas no Financeiro (lab/piloto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->string('baixado_por', 80)->nullable()->after('pago_em');
            $table->string('baixado_por_nome', 120)->nullable()->after('baixado_por');
            $table->string('baixa_retirada_por', 80)->nullable()->after('baixado_por_nome');
            $table->string('baixa_retirada_por_nome', 120)->nullable()->after('baixa_retirada_por');
            $table->timestamp('baixa_retirada_em')->nullable()->after('baixa_retirada_por_nome');
        });

        Schema::table('parcelas', function (Blueprint $table) {
            $table->string('baixado_por', 80)->nullable()->after('pago_em');
            $table->string('baixado_por_nome', 120)->nullable()->after('baixado_por');
            $table->string('baixa_retirada_por', 80)->nullable()->after('baixado_por_nome');
            $table->string('baixa_retirada_por_nome', 120)->nullable()->after('baixa_retirada_por');
            $table->timestamp('baixa_retirada_em')->nullable()->after('baixa_retirada_por_nome');
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropColumn([
                'baixado_por',
                'baixado_por_nome',
                'baixa_retirada_por',
                'baixa_retirada_por_nome',
                'baixa_retirada_em',
            ]);
        });

        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropColumn([
                'baixado_por',
                'baixado_por_nome',
                'baixa_retirada_por',
                'baixa_retirada_por_nome',
                'baixa_retirada_em',
            ]);
        });
    }
};
