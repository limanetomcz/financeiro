<?php

namespace App\Services\Fatura;

/**
 * Cálculo de valor por vida — regras Seridó (Fun_GeraValorMensBenefPlEmpr),
 * sem gravar tb_lancamento_mensalidade. Valor anterior vem do Financeiro.
 */
class CalcularValorVidaSeridoService
{
    /**
     * @param  array<string, mixed>  $vida  Payload do Laravel (dadosFaturaFinanceiroNovo)
     */
    public function executar(
        array $vida,
        ?float $valorAnteriorNoFinanceiro,
        string $competencia,
        ?int $mesReajustePlano = null,
        ?string $dtInclPlano = null,
        float $percentualReajuste = 0.0,
    ): float {
        $precoTabela = round((float) data_get($vida, 'preco.valor', 0), 2);
        $mudouTp = (bool) ($vida['tipopag_mudou_nesta_referencia'] ?? false);
        $primeira = $valorAnteriorNoFinanceiro === null;

        if ($primeira || $mudouTp) {
            $valor = $precoTabela;
        } else {
            $valor = round($valorAnteriorNoFinanceiro, 2);
        }

        if ($percentualReajuste != 0.0 && $mesReajustePlano !== null) {
            $mesComp = (int) substr($competencia, 5, 2);
            if ($mesComp === (int) $mesReajustePlano) {
                $refIncl = $dtInclPlano
                    ? str_replace('-', '', substr($dtInclPlano, 0, 7))
                    : null;
                $refComp = str_replace('-', '', $competencia);
                if ($refIncl === null || $refIncl !== $refComp) {
                    $valor = round($valor * (1 + ($percentualReajuste / 100)), 2);
                }
            }
        }

        return round($valor, 2);
    }

    public function chaveVida(array $vida): string
    {
        return implode('.', [
            $vida['federac'] ?? '',
            $vida['cooper'] ?? '',
            $vida['plano'] ?? '',
            $vida['familia'] ?? '',
            $vida['depend'] ?? '',
        ]);
    }
}
