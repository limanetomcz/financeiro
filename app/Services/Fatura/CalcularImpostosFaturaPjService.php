<?php

namespace App\Services\Fatura;

/**
 * Calcula retenções da fatura PJ a partir da composição do plano (legado Ilhéus/Oracle).
 */
class CalcularImpostosFaturaPjService
{
    /**
     * @param  array{
     *   valor_bruto: float|int|string,
     *   flags: array{irrf?: bool, iss?: bool, piscofins?: bool, csll?: bool, inss?: bool},
     *   aliquotas: array{irrf?: float|int, iss?: float|int, piscofins?: float|int, csll?: float|int, inss?: float|int},
     *   regras?: array{irrf_minimo?: float|int, piscofins_csll_bruto_minimo?: float|int, inss_base_percentual?: float|int},
     *   chave_plano?: string|null
     * }  $dados
     * @return list<array{codigo: string, descricao: string, natureza: string, valor: float}>
     */
    public function executar(array $dados): array
    {
        $bruto = round((float) ($dados['valor_bruto'] ?? 0), 2);
        $flags = $dados['flags'] ?? [];
        $aliquotas = $dados['aliquotas'] ?? [];
        $regras = $dados['regras'] ?? [];
        $chavePlano = (string) ($dados['chave_plano'] ?? '');

        $irrfMinimo = (float) ($regras['irrf_minimo'] ?? 10);
        $pisoPisCsll = (float) ($regras['piscofins_csll_bruto_minimo'] ?? 5000);
        $inssBasePerc = (float) ($regras['inss_base_percentual'] ?? 60);

        $lancamentos = [];

        if ($bruto <= 0) {
            return $lancamentos;
        }

        if (! empty($flags['irrf'])) {
            $valor = round(((float) ($aliquotas['irrf'] ?? 0)) * $bruto / 100, 2);
            if ($valor < $irrfMinimo) {
                $valor = 0.0;
            }
            if ($valor > 0) {
                $lancamentos[] = [
                    'codigo' => 'ir',
                    'descricao' => 'IRRF',
                    'natureza' => 'retencao',
                    'valor' => $valor,
                ];
            }
        }

        if (! empty($flags['iss'])) {
            $valor = round(((float) ($aliquotas['iss'] ?? 0)) * $bruto / 100, 2);
            if ($valor > 0) {
                $lancamentos[] = [
                    'codigo' => 'iss',
                    'descricao' => 'ISS',
                    'natureza' => 'retencao',
                    'valor' => $valor,
                ];
            }
        }

        if ($bruto > $pisoPisCsll) {
            if (! empty($flags['piscofins'])) {
                $valor = round(((float) ($aliquotas['piscofins'] ?? 0)) * $bruto / 100, 2);
                if ($valor > 0) {
                    $lancamentos[] = [
                        'codigo' => 'piscofins',
                        'descricao' => 'PIS/COFINS',
                        'natureza' => 'retencao',
                        'valor' => $valor,
                    ];
                }
            }
            if (! empty($flags['csll'])) {
                $valor = round(((float) ($aliquotas['csll'] ?? 0)) * $bruto / 100, 2);
                if ($valor > 0) {
                    $lancamentos[] = [
                        'codigo' => 'csll',
                        'descricao' => 'CSLL',
                        'natureza' => 'retencao',
                        'valor' => $valor,
                    ];
                }
            }
        }

        if (! empty($flags['inss'])) {
            $baseInss = $bruto * ($inssBasePerc / 100);
            $valor = round(((float) ($aliquotas['inss'] ?? 0)) * $baseInss / 100, 2);
            if ($valor > 0) {
                // Legado: plano 0497 joga INSS em "outros descontos"
                $codigo = $chavePlano === '0497' ? 'outro_desconto' : 'inss';
                $lancamentos[] = [
                    'codigo' => $codigo,
                    'descricao' => $codigo === 'inss' ? 'INSS' : 'Outros descontos (INSS)',
                    'natureza' => 'retencao',
                    'valor' => $valor,
                ];
            }
        }

        return $lancamentos;
    }
}
