<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\Remessa;
use App\Services\Bancario\GerarRemessaService;

class GerarRemessaJob extends TenantJob
{
    public int $timeout = 600;

    public function __construct(
        public string $remessaId,
        ?string $clienteId = null,
    ) {
        parent::__construct($clienteId);
    }

    protected function queueGroup(): string
    {
        return 'bancario';
    }

    protected function handleForCliente(Cliente $cliente): void
    {
        $remessa = Remessa::query()->find($this->remessaId);

        if (! $remessa) {
            return;
        }

        app(GerarRemessaService::class)->processar($remessa);
    }
}
