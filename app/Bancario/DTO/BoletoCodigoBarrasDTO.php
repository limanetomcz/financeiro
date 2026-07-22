<?php

namespace App\Bancario\DTO;

/**
 * Dados Febraban do boleto (independente do banco).
 * Cada adapter preenche conforme a regra do banco.
 */
final class BoletoCodigoBarrasDTO
{
    public function __construct(
        public readonly string $codigoBarras,
        public readonly string $linhaDigitavel,
        public readonly string $linhaDigitavelFormatada,
        public readonly string $fatorVencimento,
        public readonly string $campoLivre,
        public readonly string $nossoNumeroExibicao,
        public readonly string $agenciaCodigoBeneficiario,
        public readonly string $codigoBancoFormatado,
    ) {}
}
