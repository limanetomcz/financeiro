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
        ];
    }
}
