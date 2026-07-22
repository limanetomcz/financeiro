<?php

namespace App\Support\Cliente;

use App\Models\Cliente;

class ClienteConfig
{
    public const MODO_MENSAL_EXIGIVEL = 'mensal_exigivel';

    public const MODO_TODAS_ABERTAS = 'todas_abertas';

    public static function modoGeracaoParcelas(Cliente $cliente): string
    {
        $modo = data_get($cliente->config, 'parcelas.modo_geracao', self::MODO_MENSAL_EXIGIVEL);

        return in_array($modo, [self::MODO_MENSAL_EXIGIVEL, self::MODO_TODAS_ABERTAS], true)
            ? $modo
            : self::MODO_MENSAL_EXIGIVEL;
    }

    public static function diasAposVencimento(Cliente $cliente): int
    {
        return max(0, (int) data_get($cliente->config, 'elegibilidade.dias_apos_vencimento', 0));
    }

    public static function minParcelasVencidas(Cliente $cliente): int
    {
        return max(1, (int) data_get($cliente->config, 'elegibilidade.min_parcelas_vencidas', 1));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pjLancamentos(Cliente $cliente): array
    {
        $itens = data_get($cliente->config, 'pj.lancamentos', self::padraoSerido()['pj']['lancamentos']);

        return collect($itens)
            ->filter(fn ($i) => ($i['ativo'] ?? true) === true)
            ->sortBy('ordem')
            ->values()
            ->all();
    }

    public static function pjBoletoUsaValor(Cliente $cliente): string
    {
        $v = data_get($cliente->config, 'pj.boleto_usa_valor', 'liquido');

        return in_array($v, ['liquido', 'bruto'], true) ? $v : 'liquido';
    }

    public static function pjDiaVencimentoPadrao(Cliente $cliente): int
    {
        return max(1, min(28, (int) data_get($cliente->config, 'pj.dia_vencimento_padrao', 10)));
    }

    public static function pjBloquearBeneficiariosSeEmpresaInadimplente(Cliente $cliente): bool
    {
        return (bool) data_get($cliente->config, 'pj.bloquear_beneficiarios_se_empresa_inadimplente', true);
    }

    /** Quantas faturas vencidas tornam a empresa inadimplente. */
    public static function pjMinFaturasVencidasInadimplencia(Cliente $cliente): int
    {
        return max(1, (int) data_get($cliente->config, 'pj.min_faturas_vencidas_inadimplencia', 1));
    }

    /**
     * Se a empresa já tem este número de faturas em aberto (aberta/em_cobranca),
     * não gera nova fatura.
     */
    public static function pjMaxFaturasAbertasParaGerar(Cliente $cliente): int
    {
        return max(1, (int) data_get($cliente->config, 'pj.max_faturas_abertas_para_gerar', 1));
    }

    /**
     * @return array<string, mixed>
     */
    public static function padraoSerido(): array
    {
        return [
            'elegibilidade' => [
                'dias_apos_vencimento' => 0,
                'min_parcelas_vencidas' => 1,
            ],
            'parcelas' => [
                'modo_geracao' => self::MODO_MENSAL_EXIGIVEL,
            ],
            'bancario' => [
                'banco' => 'sicredi',
                'meios' => ['boleto', 'pix'],
            ],
            'pj' => [
                'ciclo' => 'mensal',
                'boleto_usa_valor' => 'liquido',
                'dia_vencimento_padrao' => 10,
                'bloquear_beneficiarios_se_empresa_inadimplente' => true,
                'min_faturas_vencidas_inadimplencia' => 1,
                'max_faturas_abertas_para_gerar' => 1,
                'lancamentos' => [
                    [
                        'codigo' => 'mensalidades',
                        'descricao' => 'Mensalidades',
                        'natureza' => 'base',
                        'origem' => 'soma_parcelas',
                        'ativo' => true,
                        'ordem' => 1,
                    ],
                    [
                        'codigo' => 'ir',
                        'descricao' => 'IR',
                        'natureza' => 'retencao',
                        'origem' => 'manual',
                        'ativo' => true,
                        'ordem' => 2,
                    ],
                    [
                        'codigo' => 'iss',
                        'descricao' => 'ISS',
                        'natureza' => 'retencao',
                        'origem' => 'manual',
                        'ativo' => true,
                        'ordem' => 3,
                    ],
                    [
                        'codigo' => 'pis',
                        'descricao' => 'PIS',
                        'natureza' => 'retencao',
                        'origem' => 'manual',
                        'ativo' => false,
                        'ordem' => 4,
                    ],
                    [
                        'codigo' => 'cofins',
                        'descricao' => 'COFINS',
                        'natureza' => 'retencao',
                        'origem' => 'manual',
                        'ativo' => false,
                        'ordem' => 5,
                    ],
                ],
            ],
        ];
    }
}
