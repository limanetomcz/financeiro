<?php

namespace App\Bancario\Selecao;

use App\Bancario\DTO\FiltroRemessa;
use App\Contracts\Bancario\FonteTituloRemessaInterface;
use App\Contracts\Bancario\TitulosRemessaSelectorInterface;
use Illuminate\Support\Collection;

/**
 * Substitui o UNION ALL monstruoso da view por composição explícita.
 */
class CompositeTitulosRemessaSelector implements TitulosRemessaSelectorInterface
{
    /** @param  list<FonteTituloRemessaInterface>  $fontes */
    public function __construct(
        private readonly array $fontes,
    ) {}

    public function selecionar(FiltroRemessa $filtro): Collection
    {
        $todos = collect();

        foreach ($this->fontes as $fonte) {
            $todos = $todos->concat($fonte->buscar($filtro));
        }

        return $todos
            ->unique(fn ($t) => $t->nossoNumero.'|'.$t->operacao->value)
            ->sortBy(fn ($t) => $t->nossoNumero)
            ->values();
    }
}
