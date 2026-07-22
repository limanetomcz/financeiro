<?php

namespace App\Contracts\Bancario;

use App\Bancario\DTO\FiltroRemessa;
use App\Bancario\DTO\TituloRemessa;
use Illuminate\Support\Collection;

/**
 * Um "braço" do antigo UNION ALL da view_remessa_boletos.
 * Cada fonte = um tipo/operação (entrada PF, consolidada, fatura, alteração…).
 */
interface FonteTituloRemessaInterface
{
    public function nome(): string;

    /**
     * @return Collection<int, TituloRemessa>
     */
    public function buscar(FiltroRemessa $filtro): Collection;
}
