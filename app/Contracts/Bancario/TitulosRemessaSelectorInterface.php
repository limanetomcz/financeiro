<?php

namespace App\Contracts\Bancario;

use App\Bancario\DTO\FiltroRemessa;
use App\Bancario\DTO\TituloRemessa;
use Illuminate\Support\Collection;

/**
 * Seleciona títulos elegíveis para um lote de remessa.
 * Extensão por banco/cooperativa: nova implementação + bind no factory.
 */
interface TitulosRemessaSelectorInterface
{
    /**
     * @return Collection<int, TituloRemessa>
     */
    public function selecionar(FiltroRemessa $filtro): Collection;
}
