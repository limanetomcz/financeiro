<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->string('chave_plano_sigoweb', 64)->nullable()->after('contratante_id');
            $table->text('mensagem_erro')->nullable()->after('status');
            $table->json('meta')->nullable()->after('mensagem_erro');
            $table->index(['cliente_id', 'chave_plano_sigoweb', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropIndex(['cliente_id', 'chave_plano_sigoweb', 'competencia']);
            $table->dropColumn(['chave_plano_sigoweb', 'mensagem_erro', 'meta']);
        });
    }
};
