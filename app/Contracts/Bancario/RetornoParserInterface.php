<?php

namespace App\Contracts\Bancario;

use App\Bancario\DTO\OcorrenciaRetorno;
use Illuminate\Support\Collection;

interface RetornoParserInterface
{
    /**
     * @return Collection<int, OcorrenciaRetorno>
     */
    public function parse(string $conteudo): Collection;
}
