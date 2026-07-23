<?php

namespace App\Services\Fatura;

use App\Models\Fatura;
use App\Models\FaturaNumeroSequencia;

/**
 * Número oficial: AAAAMM/SSSS (ex.: 202612/0001).
 *
 * - Sequência por tenant (cliente) + competência AAAAMM.
 * - Cada tenant tem a sua (Seridó e outra coop podem ter 202612/0001).
 * - Número é queimado na alocação: remover fatura não devolve a sequência.
 *
 * Chamar dentro de transação (ex.: ao abrir a fatura).
 */
class AlocarNumeroFaturaService
{
    public function executar(Fatura $fatura): string
    {
        if ($fatura->numero) {
            return $fatura->numero;
        }

        $ref = str_replace('-', '', (string) $fatura->competencia);
        if (! preg_match('/^\d{6}$/', $ref)) {
            $ref = now()->format('Ym');
        }

        $seqRow = FaturaNumeroSequencia::query()
            ->where('cliente_id', $fatura->cliente_id)
            ->where('referencia', $ref)
            ->lockForUpdate()
            ->first();

        if (! $seqRow) {
            $seqRow = FaturaNumeroSequencia::query()->create([
                'cliente_id' => $fatura->cliente_id,
                'referencia' => $ref,
                'ultimo' => 0,
            ]);
            $seqRow = FaturaNumeroSequencia::query()
                ->whereKey($seqRow->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $seq = ((int) $seqRow->ultimo) + 1;
        $seqRow->update(['ultimo' => $seq]);

        $numero = $ref.'/'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        $fatura->update(['numero' => $numero]);

        return $numero;
    }
}
