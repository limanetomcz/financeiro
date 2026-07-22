<?php

namespace App\Services\Parcela;

use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;

/**
 * Port de uniod.FUN_CALCULAR_JUROS_MULTA (tipo M / mensalidade PF).
 *
 * Retorno Oracle = valor total (principal + juros + multa).
 *
 * Regras:
 * - Só cobra se pago_em > vencimento, não dispensado e flag do tenant ativa
 * - Carência FDS: se vencimento cai Sáb/Dom e pagamento em até 2 dias
 *   caindo em Sáb/Dom/Seg → sem encargos
 * - Juros: round(0.033 * dias * valor) / 100   (0,033% a.d. hardcoded no Oracle)
 * - Multa: valor * 0.02
 * - Total: trunc(juros + multa + valor, 2)
 *
 * Na baixa usamos a data de recebimento (não SYSDATE) para atraso e dias.
 */
class CalcularJurosMultaService
{
    /**
     * @return array{
     *   atrasada: bool,
     *   dias_atraso: int,
     *   valor_principal: float,
     *   valor_juros: float,
     *   valor_multa: float,
     *   valor_encargos: float,
     *   valor_total: float,
     *   percentual_juros_dia: float,
     *   percentual_multa: float,
     *   carencia_fds_aplicada: bool,
     *   cobrar_habilitado: bool,
     *   vencimento: string,
     *   pago_em: string
     * }
     */
    public function calcular(float $valorPrincipal, string $vencimento, string $pagoEm, bool $dispensado = false): array
    {
        $valorPrincipal = round($valorPrincipal, 2);
        $venc = Carbon::parse($vencimento)->startOfDay();
        $pag = Carbon::parse($pagoEm)->startOfDay();

        $dias = $pag->greaterThan($venc)
            ? (int) $venc->diffInDays($pag)
            : 0;

        $cliente = ClienteContext::get();
        $cobrar = (bool) data_get($cliente->config, 'cobranca.cobrar_multa_juros_pf', true);
        // Oracle hardcoda 0.033 e 0.02; permitimos override no config se necessário.
        $pctDia = (float) data_get($cliente->config, 'cobranca.percentual_juros_dia', 0.033);
        $pctMulta = (float) data_get(
            $cliente->config,
            'cobranca.percentual_multa',
            data_get($cliente->config, 'bancario.conta.percentual_multa', 2.0)
        );

        $base = [
            'atrasada' => $dias > 0,
            'dias_atraso' => $dias,
            'valor_principal' => $valorPrincipal,
            'valor_juros' => 0.0,
            'valor_multa' => 0.0,
            'valor_encargos' => 0.0,
            'valor_total' => $valorPrincipal,
            'percentual_juros_dia' => $pctDia,
            'percentual_multa' => $pctMulta,
            'carencia_fds_aplicada' => false,
            'cobrar_habilitado' => $cobrar,
            'vencimento' => $venc->toDateString(),
            'pago_em' => $pag->toDateString(),
        ];

        if ($dias <= 0 || $dispensado || ! $cobrar) {
            return $base;
        }

        // Oracle to_char(date,'D'): 1=Domingo … 7=Sábado
        if ($this->venceuEmFimDeSemana($venc) && $dias <= 2 && $this->pagamentoEmCarenciaFds($pag)) {
            $base['carencia_fds_aplicada'] = true;

            return $base;
        }

        // trunc((round(0.033 * dias * valor) / 100) + (valor * 0.02) + valor, 2)
        $juros = $this->trunc2(round($pctDia * $dias * $valorPrincipal) / 100);
        $multa = $this->trunc2($valorPrincipal * ($pctMulta / 100));
        $total = $this->trunc2($juros + $multa + $valorPrincipal);

        $base['valor_juros'] = $juros;
        $base['valor_multa'] = $multa;
        $base['valor_encargos'] = $this->trunc2($juros + $multa);
        $base['valor_total'] = $total;

        return $base;
    }

    private function venceuEmFimDeSemana(Carbon $venc): bool
    {
        // format('w'): 0=Domingo, 6=Sábado
        return in_array((int) $venc->format('w'), [0, 6], true);
    }

    private function pagamentoEmCarenciaFds(Carbon $pag): bool
    {
        // Oracle: sysdate em ('7','1','2') = Sáb, Dom, Seg
        return in_array((int) $pag->format('w'), [0, 6, 1], true);
    }

    /** Oracle trunc(x, 2) para positivo. */
    private function trunc2(float $valor): float
    {
        return floor(($valor * 100) + 1e-8) / 100;
    }
}
