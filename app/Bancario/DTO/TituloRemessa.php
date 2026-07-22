<?php

namespace App\Bancario\DTO;

use App\Enums\OperacaoRemessa;
use Carbon\CarbonInterface;

readonly class TituloRemessa
{
    public function __construct(
        public string $cobrancaId,
        public string $nossoNumero,
        public ?string $numeroRegistro,
        public OperacaoRemessa $operacao,
        public float $valor,
        public float $valorJurosDia,
        public float $valorMulta,
        public CarbonInterface $vencimento,
        public CarbonInterface $dataEmissao,
        public string $tipoBoleto,
        public int $diasDevolucao,
        public string $codigoMulta,
        public PagadorRemessa $pagador,
    ) {}
}
