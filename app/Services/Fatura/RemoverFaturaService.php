<?php

namespace App\Services\Fatura;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Models\RemessaItem;
use Illuminate\Support\Facades\DB;

class RemoverFaturaService
{
    /**
     * Exclusão lógica da fatura (deleted_at). Mantém número queimado e lançamentos.
     * Cancela cobrança/boleto vinculado (se não estiver paga).
     *
     * @return array{message: string, apagados: array{fatura: int, lancamentos: int, cobranca_cancelada: int, remessa_itens_desvinculados: int}}
     */
    public function executar(Fatura $fatura): array
    {
        return DB::transaction(function () use ($fatura) {
            $fatura = Fatura::query()
                ->with(['lancamentos', 'cobranca'])
                ->whereKey($fatura->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($fatura->status === StatusFatura::Paga) {
                throw new DominioException('Não é possível remover fatura já paga.');
            }

            $cobranca = $fatura->cobranca;
            if ($cobranca && $cobranca->status === StatusCobranca::Paga) {
                throw new DominioException('Não é possível remover: cobrança/boleto já liquidado.');
            }

            $lancamentos = $fatura->lancamentos()->count();
            $remessaItens = 0;
            $cobrancaCancelada = 0;

            if ($cobranca) {
                $remessaItens = RemessaItem::query()
                    ->where('cobranca_id', $cobranca->id)
                    ->update(['cobranca_id' => null]);

                $cobranca->update(['status' => StatusCobranca::Cancelada]);
                $cobranca->delete();
                $cobrancaCancelada = 1;
            }

            $fatura->parcelas()->detach();
            $fatura->update(['status' => StatusFatura::Cancelada]);
            $fatura->delete();

            return [
                'message' => 'Fatura excluída (lógico).',
                'apagados' => [
                    'fatura' => 1,
                    'lancamentos' => $lancamentos,
                    'cobranca_cancelada' => $cobrancaCancelada,
                    'remessa_itens_desvinculados' => $remessaItens,
                ],
            ];
        });
    }
}
