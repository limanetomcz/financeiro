<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tabelas = [
        'contratantes',
        'contratos',
        'parcelas',
        'cobrancas',
        'remessas',
        'remessa_itens',
        'retornos_bancarios',
        'retorno_bancario_itens',
        'fatura_lancamentos',
        'contrato_beneficiarios',
        'parcela_beneficiarios',
        'locais_pagamento',
        'taxas_local_pagamento',
    ];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabelas as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
