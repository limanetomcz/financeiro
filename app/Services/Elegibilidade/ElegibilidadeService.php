<?php

namespace App\Services\Elegibilidade;

use App\Enums\StatusContrato;
use App\Enums\StatusFatura;
use App\Enums\StatusParcela;
use App\Enums\TipoContratante;
use App\Models\Contratante;
use App\Models\Fatura;
use App\Models\Parcela;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;

class ElegibilidadeService
{
    /**
     * @return array{
     *   pode_usar_plano: bool,
     *   motivo: ?string,
     *   parcelas_vencidas: int,
     *   faturas_vencidas: int,
     *   parametros: array{
     *     dias_apos_vencimento: int,
     *     min_parcelas_vencidas: int,
     *     min_faturas_vencidas_inadimplencia: int,
     *     max_faturas_abertas_para_gerar: int
     *   }
     * }
     */
    public function avaliarPorChaveSigoweb(string $chaveSigoweb, ?int $diasCarencia = null, ?int $minParcelas = null): array
    {
        $cliente = ClienteContext::get();
        $dias = $diasCarencia ?? ClienteConfig::diasAposVencimento($cliente);
        $minParcelasCfg = $minParcelas ?? ClienteConfig::minParcelasVencidas($cliente);
        $minFaturas = ClienteConfig::pjMinFaturasVencidasInadimplencia($cliente);

        $parametros = [
            'dias_apos_vencimento' => $dias,
            'min_parcelas_vencidas' => $minParcelasCfg,
            'min_faturas_vencidas_inadimplencia' => $minFaturas,
            'max_faturas_abertas_para_gerar' => ClienteConfig::pjMaxFaturasAbertasParaGerar($cliente),
        ];

        $baseNegado = fn (string $motivo, int $parcelas = 0, int $faturas = 0) => [
            'pode_usar_plano' => false,
            'motivo' => $motivo,
            'parcelas_vencidas' => $parcelas,
            'faturas_vencidas' => $faturas,
            'parametros' => $parametros,
        ];

        $contratante = Contratante::query()
            ->where('chave_sigoweb', $chaveSigoweb)
            ->first();

        if (! $contratante) {
            return $baseNegado('Contratante não encontrado no Financeiro.');
        }

        $limite = Carbon::today()->subDays($dias)->toDateString();

        // PF vinculado a empresa: herda inadimplência da PJ se configurado
        if (
            $contratante->tipo === TipoContratante::Pf
            && $contratante->empresa_id
            && ClienteConfig::pjBloquearBeneficiariosSeEmpresaInadimplente($cliente)
        ) {
            $faturasEmpresa = $this->contarFaturasVencidas($contratante->empresa_id, $limite);
            if ($faturasEmpresa >= $minFaturas) {
                return $baseNegado('Empresa inadimplente — atendimento bloqueado.', 0, $faturasEmpresa);
            }
        }

        if ($contratante->tipo === TipoContratante::Pj) {
            $faturasVencidas = $this->contarFaturasVencidas($contratante->id, $limite);
            if ($faturasVencidas >= $minFaturas) {
                return $baseNegado(
                    "Há {$faturasVencidas} fatura(s) vencida(s) (mínimo para inadimplência: {$minFaturas}).",
                    0,
                    $faturasVencidas
                );
            }

            return [
                'pode_usar_plano' => true,
                'motivo' => null,
                'parcelas_vencidas' => 0,
                'faturas_vencidas' => $faturasVencidas,
                'parametros' => $parametros,
            ];
        }

        $temSuspenso = $contratante->contratos()
            ->where('status', StatusContrato::Suspenso)
            ->exists();

        if ($temSuspenso) {
            return $baseNegado('Contrato suspenso.');
        }

        $vencidas = Parcela::query()
            ->whereHas('contrato', function ($q) use ($contratante) {
                $q->where('contratante_id', $contratante->id)
                    ->where('status', StatusContrato::Ativo);
            })
            ->whereIn('status', [StatusParcela::Aberta, StatusParcela::EmCobranca])
            ->whereDate('vencimento', '<', $limite)
            ->count();

        if ($vencidas >= $minParcelasCfg) {
            return $baseNegado(
                "Há {$vencidas} parcela(s) vencida(s) (mínimo para bloquear: {$minParcelasCfg}).",
                $vencidas
            );
        }

        return [
            'pode_usar_plano' => true,
            'motivo' => null,
            'parcelas_vencidas' => $vencidas,
            'faturas_vencidas' => 0,
            'parametros' => $parametros,
        ];
    }

    private function contarFaturasVencidas(string $empresaId, string $limite): int
    {
        return Fatura::query()
            ->where('contratante_id', $empresaId)
            ->whereIn('status', [StatusFatura::Aberta, StatusFatura::EmCobranca])
            ->whereDate('vencimento', '<', $limite)
            ->count();
    }
}
