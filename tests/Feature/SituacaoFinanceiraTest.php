<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Models\Cliente;
use App\Services\Contrato\CriarContratoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SituacaoFinanceiraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        $cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);
        ClienteContext::set($cliente);

        app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-FIN',
                'tipo' => 'pf',
                'nome' => 'Benef Teste',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 120,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_retorna_resumo_financeiro_do_contratante(): void
    {
        $service = app(\App\Services\Financeiro\SituacaoFinanceiraService::class);
        $resultado = $service->porChaveSigoweb('BEN-FIN');

        $this->assertTrue($resultado['encontrado']);
        $this->assertEquals('BEN-FIN', $resultado['contratante']['chave_sigoweb']);
        $this->assertArrayHasKey('elegibilidade', $resultado);
        $this->assertGreaterThan(0, $resultado['parcelas']['abertas_qtd']);
        $this->assertGreaterThan(0, $resultado['parcelas']['vencidas_qtd']);
        $this->assertGreaterThan(0, $resultado['saldo_em_aberto']);
    }

    public function test_nao_encontrado(): void
    {
        $resultado = app(\App\Services\Financeiro\SituacaoFinanceiraService::class)
            ->porChaveSigoweb('INEXISTENTE');

        $this->assertFalse($resultado['encontrado']);
    }
}
