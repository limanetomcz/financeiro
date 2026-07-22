<?php

namespace App\Contracts\Bancario;

use App\Bancario\DTO\ContaCobranca;
use App\Models\Remessa;
use Illuminate\Support\Collection;

/**
 * Monta o conteúdo textual do arquivo CNAB (ou equivalente).
 */
interface RemessaLayoutGeneratorInterface
{
    /**
     * @param  Collection<int, \App\Models\RemessaItem>  $itens
     */
    public function gerar(Remessa $remessa, ContaCobranca $conta, Collection $itens): string;
}
