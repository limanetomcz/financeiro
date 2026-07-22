<?php

namespace App\Contracts\Bancario;

use App\Bancario\DTO\BoletoCodigoBarrasDTO;
use App\Bancario\DTO\ContaCobranca;
use Carbon\CarbonInterface;

/**
 * Adapter de boleto PDF por banco (OCP).
 * Novo banco = nova classe + registro na fábrica; o orquestrador não muda.
 */
interface BancoBoletoAdapterInterface
{
    public function codigoBanco(): string;

    /**
     * View Blade do layout (ex.: boletos.sicredi).
     */
    public function viewTemplate(): string;

    public function montarCodigoBarras(
        ContaCobranca $conta,
        string $nossoNumero,
        CarbonInterface $vencimento,
        float $valor,
    ): BoletoCodigoBarrasDTO;
}
