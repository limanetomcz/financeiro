<?php

namespace App\Contracts\Bancario;

use App\Bancario\DTO\ContaCobranca;
use App\Models\Remessa;
use Carbon\CarbonInterface;

interface RemessaNomeArquivoInterface
{
    public function nomear(Remessa $remessa, ContaCobranca $conta, CarbonInterface $quando): string;
}
