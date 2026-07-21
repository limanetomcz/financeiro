<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('perfil_pagamento', 30)->default('boleto_parcelado')->after('tipo');
            $table->string('modo_emissao', 20)->default('escalonada')->after('perfil_pagamento');
        });

        Schema::table('parcelas', function (Blueprint $table) {
            $table->date('emitida_em')->nullable()->after('vencimento');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['perfil_pagamento', 'modo_emissao']);
        });

        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropColumn('emitida_em');
        });
    }
};
