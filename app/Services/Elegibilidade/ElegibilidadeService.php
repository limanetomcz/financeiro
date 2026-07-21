<?php

namespace App\Services\Elegibilidade;

use App\Enums\StatusContrato;
use App\Enums\StatusParcela;
use App\Models\Contratante;
use App\Models\Parcela;
use Carbon\Carbon;

class ElegibilidadeService
{
    /**
     * MVP: bloqueia se houver parcela vencida em aberto/em cobrança,
     * ou contrato ativo marcado como suspenso.
     *
     * @return array{pode_usar_plano: bool, motivo: ?string, parcelas_vencidas: int}
     */
    public function avaliarPorChaveSigoweb(string $chaveSigoweb, int $diasCarencia = 0): array
    {
        $contratante = Contratante::query()
            ->where('chave_sigoweb', $chaveSigoweb)
            ->first();

        if (! $contratante) {
            return [
                'pode_usar_plano' => false,
                'motivo' => 'Contratante não encontrado no Financeiro.',
                'parcelas_vencidas' => 0,
            ];
        }

        $temSuspenso = $contratante->contratos()
            ->where('status', StatusContrato::Suspenso)
            ->exists();

        if ($temSuspenso) {
            return [
                'pode_usar_plano' => false,
                'motivo' => 'Contrato suspenso.',
                'parcelas_vencidas' => 0,
            ];
        }

        $limite = Carbon::today()->subDays($diasCarencia)->toDateString();

        $vencidas = Parcela::query()
            ->whereHas('contrato', function ($q) use ($contratante) {
                $q->where('contratante_id', $contratante->id)
                    ->where('status', StatusContrato::Ativo);
            })
            ->whereIn('status', [StatusParcela::Aberta, StatusParcela::EmCobranca])
            ->whereDate('vencimento', '<', $limite)
            ->count();

        if ($vencidas > 0) {
            return [
                'pode_usar_plano' => false,
                'motivo' => "Há {$vencidas} parcela(s) vencida(s).",
                'parcelas_vencidas' => $vencidas,
            ];
        }

        return [
            'pode_usar_plano' => true,
            'motivo' => null,
            'parcelas_vencidas' => 0,
        ];
    }
}
