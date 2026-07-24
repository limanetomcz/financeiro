<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->date('data_emissao')->nullable()->after('competencia');
        });

        // Backfill: emissão = data de criação (legado fat_emissao ≈ momento da geração).
        DB::table('faturas')
            ->whereNull('data_emissao')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('faturas')
                        ->where('id', $row->id)
                        ->update([
                            'data_emissao' => $row->created_at
                                ? substr((string) $row->created_at, 0, 10)
                                : now()->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropColumn('data_emissao');
        });
    }
};
