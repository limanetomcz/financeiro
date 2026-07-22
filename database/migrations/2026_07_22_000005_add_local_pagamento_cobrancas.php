<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            // Espelho leve do legado tb_localpagamento (código + descrição na baixa).
            $table->string('local_pagamento', 20)->nullable()->after('meio');
            $table->string('local_pagamento_descricao', 80)->nullable()->after('local_pagamento');
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropColumn(['local_pagamento', 'local_pagamento_descricao']);
        });
    }
};
