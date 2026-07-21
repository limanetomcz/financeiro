<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusParcela;
use App\Models\Cliente;
use App\Services\Contrato\CriarContratoService;
use App\Services\Elegibilidade\ElegibilidadeService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasosEmissaoInadimplenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-01-01'));

        $cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);
        ClienteContext::set($cliente);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cartao_12x_emissao_imediata_abre_todas_hoje(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => ['chave_sigoweb' => 'C1', 'tipo' => 'pf', 'nome' => 'Cartão imediato'],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 1200,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::CartaoParcelado->value,
            'modo_emissao' => ModoEmissao::Imediata->value,
        ]);

        $this->assertEquals(12, $contrato->parcelas->where('status', StatusParcela::Aberta)->count());
        $this->assertTrue($contrato->parcelas->every(fn ($p) => $p->emitida_em?->toDateString() === '2026-01-01'));
    }

    public function test_cartao_12x_emissao_escalonada_nao_incha_cr(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => ['chave_sigoweb' => 'C2', 'tipo' => 'pf', 'nome' => 'Cartão escalonado'],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 1200,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::CartaoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);

        $this->assertEquals(1, $contrato->parcelas->where('status', StatusParcela::Aberta)->count());
        $this->assertEquals(11, $contrato->parcelas->where('status', StatusParcela::Prevista)->count());
        $this->assertNull($contrato->parcelas->firstWhere('numero', 2)?->emitida_em);
    }

    public function test_a_vista_pago_nao_fica_inadimplente(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => ['chave_sigoweb' => 'C3', 'tipo' => 'pf', 'nome' => 'À vista'],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 1200,
            'perfil_pagamento' => PerfilPagamento::AVista->value,
            'ja_pago' => true,
        ]);

        $this->assertCount(1, $contrato->parcelas);
        $this->assertEquals(StatusParcela::Paga, $contrato->parcelas->first()->status);

        Carbon::setTestNow(Carbon::parse('2026-06-15'));
        $eleg = app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('C3');
        $this->assertTrue($eleg['pode_usar_plano']);
    }

    public function test_boleto_12x_inadimplencia_mensal(): void
    {
        app(CriarContratoService::class)->executar([
            'contratante' => ['chave_sigoweb' => 'C4', 'tipo' => 'pf', 'nome' => 'Boleto'],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 1200,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);

        // ainda no dia da adesão / vencimento futuro próximo — não vencida
        $eleg = app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('C4');
        $this->assertTrue($eleg['pode_usar_plano']);

        Carbon::setTestNow(Carbon::parse('2026-02-01'));
        $eleg = app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('C4');
        $this->assertFalse($eleg['pode_usar_plano']);
        $this->assertGreaterThanOrEqual(1, $eleg['parcelas_vencidas']);
    }
}
