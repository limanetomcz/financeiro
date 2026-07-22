<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Models\Cliente;
use App\Models\Contratante;
use App\Models\Contrato;
use App\Services\Contrato\CriarContratoService;
use App\Services\Lab\LimparFinanceiroContratanteService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabLimparFinanceiroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-15'));
        config(['financeiro.lab_limpeza_habilitada' => true]);

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
                'chave_sigoweb' => 'BEN-LAB',
                'tipo' => 'pf',
                'nome' => 'Lab Teste',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-LAB',
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

    public function test_limpa_contratante_e_relacionados(): void
    {
        $this->assertTrue(Contratante::query()->where('chave_sigoweb', 'BEN-LAB')->exists());
        $this->assertGreaterThan(0, Contrato::query()->count());

        $resultado = app(LimparFinanceiroContratanteService::class)
            ->porChaveSigoweb('BEN-LAB');

        $this->assertTrue($resultado['encontrado']);
        $this->assertSame(1, $resultado['apagados']['contratante']);
        $this->assertSame(12, $resultado['apagados']['parcelas']);
        $this->assertFalse(Contratante::query()->where('chave_sigoweb', 'BEN-LAB')->exists());
        $this->assertSame(0, Contrato::query()->count());
    }

    public function test_nada_a_limpar(): void
    {
        $resultado = app(LimparFinanceiroContratanteService::class)
            ->porChaveSigoweb('INEXISTENTE');

        $this->assertFalse($resultado['encontrado']);
    }

    public function test_bloqueia_quando_desabilitado(): void
    {
        config(['financeiro.lab_limpeza_habilitada' => false]);

        $this->expectException(\App\Exceptions\DominioException::class);

        app(LimparFinanceiroContratanteService::class)->porChaveSigoweb('BEN-LAB');
    }
}
