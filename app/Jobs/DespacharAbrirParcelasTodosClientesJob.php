<?php

namespace App\Jobs;

use App\Models\Cliente;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DespacharAbrirParcelasTodosClientesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $referencia = null)
    {
        $this->onQueue(config('financeiro.queues.default', 'default'));
    }

    public function handle(): void
    {
        Cliente::query()
            ->where('ativo', true)
            ->orderBy('codigo_cooperativa')
            ->each(function (Cliente $cliente) {
                AbrirParcelasExigiveisJob::dispatch($cliente->id, $this->referencia);
            });
    }
}
