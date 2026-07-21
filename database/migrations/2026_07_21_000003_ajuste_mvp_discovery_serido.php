<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('tipo', 10)->default('pf')->after('contratante_id'); // pf|pj
        });

        Schema::table('cobrancas', function (Blueprint $table) {
            $table->decimal('valor_principal', 12, 2)->default(0)->after('tipo');
            $table->decimal('valor_juros', 12, 2)->default(0)->after('valor_principal');
            $table->decimal('valor_multa', 12, 2)->default(0)->after('valor_juros');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropColumn(['valor_principal', 'valor_juros', 'valor_multa']);
        });
    }
};
