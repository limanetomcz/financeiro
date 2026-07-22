<?php

namespace App\Bancario\DTO;

final class OcorrenciaRetorno
{
    public function __construct(
        public readonly int $linha,
        public readonly string $codigoMovimento,
        public readonly string $nossoNumero,
        public readonly string $numeroRegistro,
        public readonly ?string $vencimento,
        public readonly ?string $pagoEm,
        public readonly ?float $valorPago,
        public readonly ?string $motivoRejeicao,
        public readonly string $linhaT,
        public readonly ?string $linhaU = null,
        public readonly ?float $valorJuros = null,
    ) {}
}
