<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Services\Fatura\ProcessarFaturaPjService;

class ProcessarFaturaPjJob extends TenantJob
{
    public int $timeout = 600;

    /**
     * @param  array<string, mixed>|null  $dadosOverride
     */
    public function __construct(
        public string $faturaId,
        public ?string $bearerToken = null,
        public ?array $dadosOverride = null,
        ?string $clienteId = null,
    ) {
        parent::__construct($clienteId);
    }

    protected function queueGroup(): string
    {
        return 'cobranca';
    }

    protected function handleForCliente(Cliente $cliente): void
    {
        app(ProcessarFaturaPjService::class)->executar(
            $this->faturaId,
            $this->bearerToken,
            $this->dadosOverride,
        );
    }
}
