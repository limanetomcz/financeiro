<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->string('numero', 20)->nullable()->after('chave_plano_sigoweb');
            $table->unique(['cliente_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropUnique(['cliente_id', 'numero']);
            $table->dropColumn('numero');
        });
    }
};
