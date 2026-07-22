<?php

namespace App\Bancario\Sicredi;

use App\Enums\AcaoRetornoItem;

/**
 * Mapa Febraban CNAB 240 (Sicredi), validado com .CRT Seridó:
 * - `08012930` → 02 / 06 / 28
 * - `08012722` → 09 (exclusão) / 06 / 28 — bate com relatório Sigoweb
 */
final class SicrediCodigosMovimentoRetorno
{
    /** @var list<string> */
    public const LIQUIDACAO = ['06', '17'];

    /** Baixa/exclusão de título pelo banco (não é pagamento). */
    /** @var list<string> */
    public const EXCLUSAO = ['09', '10'];

    /** @var list<string> */
    public const CONFIRMACAO_ENTRADA = ['02'];

    /** @var list<string> */
    public const REJEICAO = ['03'];

    /** Tarifa/custas do banco — só registra, não liquida. */
    /** @var list<string> */
    public const TARIFA = ['28'];

    public static function acao(string $codigo): AcaoRetornoItem
    {
        $codigo = str_pad(trim($codigo), 2, '0', STR_PAD_LEFT);

        if (in_array($codigo, self::LIQUIDACAO, true)) {
            return AcaoRetornoItem::Liquidar;
        }

        if (in_array($codigo, self::EXCLUSAO, true)) {
            return AcaoRetornoItem::ExcluirTitulo;
        }

        if (in_array($codigo, self::CONFIRMACAO_ENTRADA, true)) {
            return AcaoRetornoItem::ConfirmarEntrada;
        }

        if (in_array($codigo, self::REJEICAO, true)) {
            return AcaoRetornoItem::Rejeitar;
        }

        if (in_array($codigo, self::TARIFA, true)) {
            return AcaoRetornoItem::Registrar;
        }

        return AcaoRetornoItem::Registrar;
    }
}
