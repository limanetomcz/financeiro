<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->softDeletes();
            // Soft delete permite nova fatura na mesma competência; unicidade fica na aplicação.
            $table->dropUnique(['cliente_id', 'contratante_id', 'competencia']);
            $table->index(['cliente_id', 'contratante_id', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropIndex(['cliente_id', 'contratante_id', 'competencia']);
            $table->unique(['cliente_id', 'contratante_id', 'competencia']);
            $table->dropSoftDeletes();
        });
    }
};
