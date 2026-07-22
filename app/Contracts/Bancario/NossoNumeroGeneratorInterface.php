<?php

namespace App\Contracts\Bancario;

use App\Bancario\DTO\ContaCobranca;
use App\Models\Cobranca;

/**
 * Garante nosso número / número de registro bancário do título.
 * Trocar implementação quando portar Fun_GerarNumRegistroUnicred etc.
 */
interface NossoNumeroGeneratorInterface
{
    /**
     * @return array{nosso_numero: string, numero_registro: string}
     */
    public function garantir(Cobranca $cobranca, ContaCobranca $conta): array;
}
