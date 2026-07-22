<?php

namespace App\Services\Lab;

use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Contratante;
use App\Models\Contrato;
use App\Models\Parcela;
use Illuminate\Support\Facades\DB;

/**
 * Lab: promove todas as parcelas previstas do contratante para aberta,
 * para permitir registrar boletos / remessa do fluxo completo.
 */
class AbrirTodasParcelasContratanteLabService
{
    public function porChaveSigoweb(string $chaveSigoweb): array
    {
        if (! config('financeiro.lab_limpeza_habilitada')) {
            throw new DominioException('Limpeza de lab desabilitada neste ambiente.');
        }

        $contratante = Contratante::query()
            ->where('chave_sigoweb', $chaveSigoweb)
            ->first();

        if (! $contratante) {
            return [
                'encontrado' => false,
                'abertas' => 0,
                'message' => 'Contratante não encontrado.',
            ];
        }

        return DB::transaction(function () use ($contratante) {
            $contratoIds = Contrato::query()
                ->where('contratante_id', $contratante->id)
                ->pluck('id');

            $parcelas = Parcela::query()
                ->whereIn('contrato_id', $contratoIds)
                ->where('status', StatusParcela::Prevista)
                ->lockForUpdate()
                ->get();

            foreach ($parcelas as $parcela) {
                $parcela->update([
                    'status' => StatusParcela::Aberta,
                    'emitida_em' => $parcela->emitida_em
                        ?? $parcela->vencimento->copy()->startOfMonth()->toDateString(),
                ]);
            }

            return [
                'encontrado' => true,
                'abertas' => $parcelas->count(),
                'message' => $parcelas->count()
                    ? "Abertas {$parcelas->count()} parcela(s) prevista(s)."
                    : 'Nenhuma parcela prevista para abrir.',
            ];
        });
    }
}
