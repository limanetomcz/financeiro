<?php

namespace App\Bancario\DTO;

use Carbon\CarbonInterface;

readonly class FiltroRemessa
{
    public function __construct(
        public CarbonInterface $vencimentoInicial,
        public CarbonInterface $vencimentoFinal,
        public ContaCobranca $conta,
    ) {}
}
