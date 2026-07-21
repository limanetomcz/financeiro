<?php

namespace App\Services\Elegibilidade;

use App\Enums\StatusContrato;
use App\Enums\StatusParcela;
use App\Models\Contratante;
use App\Models\Parcela;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;

class ElegibilidadeService
{
    /**
     * Avalia se o contratante pode ser atendido (não está inadimplente).
     *
     * Parâmetros vêm de `clientes.config.elegibilidade` (e podem ser sobrescritos na chamada).
     *
     * @return array{
     *   pode_usar_plano: bool,
     *   motivo: ?string,
     *   parcelas_vencidas: int,
     *   parametros: array{dias_apos_vencimento: int, min_parcelas_vencidas: int}
     * }
     */
    public function avaliarPorChaveSigoweb(string $chaveSigoweb, ?int $diasCarencia = null, ?int $minParcelas = null): array
    {
        $cliente = ClienteContext::get();
        $dias = $diasCarencia ?? ClienteConfig::diasAposVencimento($cliente);
        $min = $minParcelas ?? ClienteConfig::minParcelasVencidas($cliente);

        $parametros = [
            'dias_apos_vencimento' => $dias,
            'min_parcelas_vencidas' => $min,
        ];

        $contratante = Contratante::query()
            ->where('chave_sigoweb', $chaveSigoweb)
            ->first();

        if (! $contratante) {
            return [
                'pode_usar_plano' => false,
                'motivo' => 'Contratante não encontrado no Financeiro.',
                'parcelas_vencidas' => 0,
                'parametros' => $parametros,
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
                'parametros' => $parametros,
            ];
        }

        $limite = Carbon::today()->subDays($dias)->toDateString();

        $vencidas = Parcela::query()
            ->whereHas('contrato', function ($q) use ($contratante) {
                $q->where('contratante_id', $contratante->id)
                    ->where('status', StatusContrato::Ativo);
            })
            ->whereIn('status', [StatusParcela::Aberta, StatusParcela::EmCobranca])
            ->whereDate('vencimento', '<', $limite)
            ->count();

        if ($vencidas >= $min) {
            return [
                'pode_usar_plano' => false,
                'motivo' => "Há {$vencidas} parcela(s) vencida(s) (mínimo para bloquear: {$min}).",
                'parcelas_vencidas' => $vencidas,
                'parametros' => $parametros,
            ];
        }

        return [
            'pode_usar_plano' => true,
            'motivo' => null,
            'parcelas_vencidas' => $vencidas,
            'parametros' => $parametros,
        ];
    }
}
