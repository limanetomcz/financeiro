<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Services\Parcela\AbrirParcelasExigiveisService;
use Carbon\Carbon;

class AbrirParcelasExigiveisJob extends TenantJob
{
    public function __construct(
        ?string $clienteId = null,
        public ?string $referencia = null,
    ) {
        parent::__construct($clienteId);
    }

    protected function queueGroup(): string
    {
        return 'cobranca';
    }

    protected function handleForCliente(Cliente $cliente): void
    {
        $data = $this->referencia ? Carbon::parse($this->referencia) : null;

        app(AbrirParcelasExigiveisService::class)->executar($data);
    }
}
