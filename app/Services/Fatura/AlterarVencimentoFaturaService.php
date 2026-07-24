<?php

namespace App\Services\Fatura;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Altera vencimento da fatura (e da cobrança vinculada, se aberta).
 * Remessa ocorrência 06 já reage a cobrancas.vencimento diferente do último envio.
 */
class AlterarVencimentoFaturaService
{
    public function executar(Fatura $fatura, string $novoVencimento): Fatura
    {
        return DB::transaction(function () use ($fatura, $novoVencimento) {
            $fatura = Fatura::query()
                ->with('cobranca')
                ->whereKey($fatura->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($fatura->status, [
                StatusFatura::Aberta,
                StatusFatura::EmCobranca,
                StatusFatura::Rascunho,
            ], true)) {
                throw new DominioException('Só é possível alterar vencimento de fatura aberta, em cobrança ou rascunho.');
            }

            if ($fatura->status === StatusFatura::Paga) {
                throw new DominioException('Não é possível alterar vencimento de fatura paga.');
            }

            $novo = Carbon::parse($novoVencimento)->startOfDay();
            $anterior = $fatura->vencimento?->toDateString();

            if ($anterior && $novo->toDateString() === $anterior) {
                throw new DominioException('Informe uma data de vencimento diferente da atual.');
            }

            $cobrancaAtualizada = false;
            if ($fatura->cobranca_id) {
                $cobranca = Cobranca::query()->whereKey($fatura->cobranca_id)->lockForUpdate()->first();
                if ($cobranca) {
                    if ($cobranca->status === StatusCobranca::Paga) {
                        throw new DominioException('Não é possível alterar vencimento: cobrança já liquidada.');
                    }
                    $cobranca->update(['vencimento' => $novo->toDateString()]);
                    $cobrancaAtualizada = true;
                }
            }

            $fatura->update([
                'vencimento' => $novo->toDateString(),
                'meta' => array_merge($fatura->meta ?? [], [
                    'vencimento_alterado' => [
                        'de' => $anterior,
                        'para' => $novo->toDateString(),
                        'cobranca_atualizada' => $cobrancaAtualizada,
                        'em' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            return $fatura->fresh(['lancamentos', 'contratante', 'cobranca']);
        });
    }
}
