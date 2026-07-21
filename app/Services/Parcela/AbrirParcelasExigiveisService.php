<?php

namespace App\Services\Parcela;

use App\Enums\StatusParcela;
use App\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AbrirParcelasExigiveisService
{
    /**
     * Promove parcelas `prevista` → `aberta` cujo vencimento cai até o fim do mês de referência.
     *
     * @return array{abertas: int}
     */
    public function executar(?Carbon $referencia = null): array
    {
        $referencia ??= Carbon::today();
        $limite = $referencia->copy()->endOfMonth()->toDateString();

        return DB::transaction(function () use ($limite) {
            $parcelas = Parcela::query()
                ->where('status', StatusParcela::Prevista)
                ->whereDate('vencimento', '<=', $limite)
                ->lockForUpdate()
                ->get();

            foreach ($parcelas as $parcela) {
                $parcela->update(['status' => StatusParcela::Aberta]);
            }

            return ['abertas' => $parcelas->count()];
        });
    }
}
