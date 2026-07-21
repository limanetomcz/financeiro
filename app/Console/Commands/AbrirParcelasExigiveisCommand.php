<?php

namespace App\Console\Commands;

use App\Jobs\AbrirParcelasExigiveisJob;
use App\Jobs\DespacharAbrirParcelasTodosClientesJob;
use App\Models\Cliente;
use App\Services\Parcela\AbrirParcelasExigiveisService;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AbrirParcelasExigiveisCommand extends Command
{
    protected $signature = 'parcelas:abrir-exigiveis
                            {--cliente= : codigo_cooperativa ou chave_sigoweb}
                            {--referencia= : data de referência Y-m-d (default: hoje)}
                            {--queue : despacha job(s) no Redis em vez de executar síncrono}';

    protected $description = 'Promove parcelas previstas a abertas (exigíveis no CR) até o mês de referência';

    public function handle(AbrirParcelasExigiveisService $service): int
    {
        $chave = $this->option('cliente');
        $referencia = $this->option('referencia');
        $viaFila = (bool) $this->option('queue');

        if ($viaFila && ! $chave) {
            DespacharAbrirParcelasTodosClientesJob::dispatch($referencia);
            $this->info('Job de despacho enfileirado para todos os clientes ativos.');

            return self::SUCCESS;
        }

        $clientes = Cliente::query()
            ->where('ativo', true)
            ->when($chave, function ($q) use ($chave) {
                $q->where(function ($q) use ($chave) {
                    $q->where('chave_sigoweb', $chave)
                        ->orWhere('codigo_cooperativa', $chave);
                });
            })
            ->get();

        if ($clientes->isEmpty()) {
            $this->error('Nenhum cliente encontrado.');

            return self::FAILURE;
        }

        foreach ($clientes as $cliente) {
            if ($viaFila) {
                AbrirParcelasExigiveisJob::dispatch($cliente->id, $referencia);
                $this->info("Cliente {$cliente->codigo_cooperativa}: job enfileirado.");

                continue;
            }

            ClienteContext::set($cliente);
            $resultado = $service->executar(
                $referencia ? Carbon::parse($referencia) : null
            );
            $this->info("Cliente {$cliente->codigo_cooperativa}: {$resultado['abertas']} parcela(s) aberta(s).");
            ClienteContext::clear();
        }

        return self::SUCCESS;
    }
}
