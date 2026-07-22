<?php

namespace App\Bancario\Sicredi;

use App\Bancario\DTO\FiltroRemessa;
use App\Bancario\Selecao\CompositeTitulosRemessaSelector;
use App\Bancario\Selecao\Fontes\AlteracaoVencimentoCobrancasFonte;
use App\Bancario\Selecao\Fontes\EntradaCobrancasFonte;
use App\Contracts\Bancario\TitulosRemessaSelectorInterface;
use Illuminate\Support\Collection;

/**
 * Fachada Sicredi: compõe as fontes que substituem a view_remessa_boletos.
 */
class SicrediTitulosRemessaSelector implements TitulosRemessaSelectorInterface
{
    private CompositeTitulosRemessaSelector $composite;

    public function __construct(
        EntradaCobrancasFonte $entrada,
        AlteracaoVencimentoCobrancasFonte $alteracao,
    ) {
        $this->composite = new CompositeTitulosRemessaSelector([
            $entrada,
            $alteracao,
        ]);
    }

    public function selecionar(FiltroRemessa $filtro): Collection
    {
        return $this->composite->selecionar($filtro);
    }
}
