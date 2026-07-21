<?php

namespace Tests\Feature;

use App\Jobs\AbrirParcelasExigiveisJob;
use App\Jobs\DespacharAbrirParcelasTodosClientesJob;
use App\Models\Cliente;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FilasTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_despacha_job_por_cliente_na_fila_cobranca(): void
    {
        Queue::fake();

        $cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);

        ClienteContext::set($cliente);
        AbrirParcelasExigiveisJob::dispatch($cliente->id, '2026-08-01');
        ClienteContext::clear();

        Queue::assertPushedOn('cobranca', AbrirParcelasExigiveisJob::class);
    }

    public function test_despacho_geral_enfileira_um_job_por_cliente_ativo(): void
    {
        Queue::fake();

        Cliente::query()->create([
            'nome' => 'Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => [],
        ]);
        Cliente::query()->create([
            'nome' => 'Outra',
            'codigo_cooperativa' => '999',
            'chave_sigoweb' => '999',
            'ativo' => true,
            'config' => [],
        ]);
        Cliente::query()->create([
            'nome' => 'Inativa',
            'codigo_cooperativa' => '000',
            'chave_sigoweb' => '000',
            'ativo' => false,
            'config' => [],
        ]);

        (new DespacharAbrirParcelasTodosClientesJob)->handle();

        Queue::assertPushed(AbrirParcelasExigiveisJob::class, 2);
    }
}
