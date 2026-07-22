<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('contador_boletos_unicred')->default(1)->after('config');
        });

        Schema::table('contratantes', function (Blueprint $table) {
            $table->string('endereco')->nullable()->after('documento');
            $table->string('bairro', 80)->nullable()->after('endereco');
            $table->string('cidade', 80)->nullable()->after('bairro');
            $table->string('cep', 10)->nullable()->after('cidade');
            $table->string('uf', 2)->nullable()->after('cep');
        });
    }

    public function down(): void
    {
        Schema::table('contratantes', function (Blueprint $table) {
            $table->dropColumn(['endereco', 'bairro', 'cidade', 'cep', 'uf']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('contador_boletos_unicred');
        });
    }
};
