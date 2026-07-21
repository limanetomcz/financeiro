<?php

namespace App\Console\Commands;

use App\Services\Parcela\AbrirParcelasExigiveisService;
use App\Support\Tenant\ClienteContext;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AbrirParcelasExigiveisCommand extends Command
{
    protected $signature = 'parcelas:abrir-exigiveis
                            {--cliente= : codigo_cooperativa ou chave_sigoweb}
                            {--referencia= : data de referência Y-m-d (default: hoje)}';

    protected $description = 'Promove parcelas previstas a abertas (exigíveis no CR) até o mês de referência';

    public function handle(AbrirParcelasExigiveisService $service): int
    {
        $chave = $this->option('cliente');
        $referencia = $this->option('referencia')
            ? Carbon::parse($this->option('referencia'))
            : null;

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
            ClienteContext::set($cliente);
            $resultado = $service->executar($referencia);
            $this->info("Cliente {$cliente->codigo_cooperativa}: {$resultado['abertas']} parcela(s) aberta(s).");
            ClienteContext::clear();
        }

        return self::SUCCESS;
    }
}
