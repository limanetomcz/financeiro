<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Support\Tenant\ClienteContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

abstract class TenantJob implements ShouldQueue
{
    use Queueable;

    public string $clienteId;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(?string $clienteId = null)
    {
        $this->clienteId = $clienteId ?? (ClienteContext::check() ? ClienteContext::id() : '');

        if ($this->clienteId === '') {
            throw new LogicException('TenantJob exige cliente_id (ClienteContext ou parâmetro).');
        }

        $this->onQueue($this->resolveQueueName());
    }

    public function handle(): void
    {
        $cliente = Cliente::query()->find($this->clienteId);

        if (! $cliente || ! $cliente->ativo) {
            return;
        }

        ClienteContext::set($cliente);

        try {
            $this->handleForCliente($cliente);
        } finally {
            ClienteContext::clear();
        }
    }

    abstract protected function handleForCliente(Cliente $cliente): void;

    /**
     * Grupo lógico da fila: default | cobranca | bancario
     */
    protected function queueGroup(): string
    {
        return 'default';
    }

    protected function resolveQueueName(): string
    {
        $group = config('financeiro.queues.'.$this->queueGroup(), $this->queueGroup());

        if (! config('financeiro.queue_por_cliente')) {
            return $group;
        }

        $cliente = Cliente::query()->find($this->clienteId);
        $codigo = $cliente?->codigo_cooperativa ?? 'unknown';

        return $group.'-cliente-'.$codigo;
    }
}
