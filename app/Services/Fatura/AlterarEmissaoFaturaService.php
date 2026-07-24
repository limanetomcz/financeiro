<?php

namespace App\Services\Fatura;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Fatura;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Espelha legado alterarEmissaoFatura (fat_emissao).
 * Nova emissão deve ser anterior à atual; fatura não pode estar paga.
 */
class AlterarEmissaoFaturaService
{
    public function executar(Fatura $fatura, string $novaDataEmissao): Fatura
    {
        return DB::transaction(function () use ($fatura, $novaDataEmissao) {
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
                throw new DominioException('Só é possível alterar emissão de fatura aberta, em cobrança ou rascunho.');
            }

            if ($fatura->cobranca && $fatura->cobranca->status === StatusCobranca::Paga) {
                throw new DominioException('Não é possível alterar emissão: cobrança já liquidada.');
            }

            $nova = Carbon::parse($novaDataEmissao)->startOfDay();
            $atual = ($fatura->data_emissao ?? $fatura->created_at?->toDateString())
                ? Carbon::parse($fatura->data_emissao ?? $fatura->created_at->toDateString())->startOfDay()
                : now()->startOfDay();

            if ($nova->greaterThanOrEqualTo($atual)) {
                throw new DominioException('A nova data de emissão deve ser anterior à data atual da fatura.');
            }

            $anterior = $atual->toDateString();
            $fatura->update([
                'data_emissao' => $nova->toDateString(),
                'meta' => array_merge($fatura->meta ?? [], [
                    'emissao_alterada' => [
                        'de' => $anterior,
                        'para' => $nova->toDateString(),
                        'em' => now()->toIso8601String(),
                    ],
                ]),
            ]);

            return $fatura->fresh(['lancamentos', 'contratante', 'cobranca']);
        });
    }
}
