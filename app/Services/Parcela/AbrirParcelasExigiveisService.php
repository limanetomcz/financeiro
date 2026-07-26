<?php

namespace App\Services\Parcela;

use App\Enums\PerfilPagamento;
use App\Enums\StatusParcela;
use App\Models\Parcela;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AbrirParcelasExigiveisService
{
    /**
     * Promove parcelas `prevista` cujo vencimento cai até o fim do mês de referência.
     * - boleto / demais: → `aberta`
     * - cartão legado: → `paga` (contratos antigos ainda com prevista; novos já nascem pagas)
     *
     * @return array{abertas: int, pagas: int}
     */
    public function executar(?Carbon $referencia = null): array
    {
        $referencia ??= Carbon::today();
        $limite = $referencia->copy()->endOfMonth()->toDateString();

        return DB::transaction(function () use ($limite) {
            $parcelas = Parcela::query()
                ->with('contrato')
                ->where('status', StatusParcela::Prevista)
                ->whereDate('vencimento', '<=', $limite)
                ->lockForUpdate()
                ->get();

            $abertas = 0;
            $pagas = 0;

            foreach ($parcelas as $parcela) {
                $emitidaEm = $parcela->emitida_em
                    ?? $parcela->vencimento->copy()->startOfMonth()->toDateString();

                $ehCartao = $parcela->contrato
                    && $parcela->contrato->perfil_pagamento === PerfilPagamento::CartaoParcelado;

                if ($ehCartao) {
                    $parcela->update([
                        'status' => StatusParcela::Paga,
                        'emitida_em' => $emitidaEm,
                        'pago_em' => $parcela->pago_em ?? Carbon::now(),
                    ]);
                    $pagas++;
                } else {
                    $parcela->update([
                        'status' => StatusParcela::Aberta,
                        'emitida_em' => $emitidaEm,
                    ]);
                    $abertas++;
                }
            }

            return [
                'abertas' => $abertas,
                'pagas' => $pagas,
            ];
        });
    }
}
