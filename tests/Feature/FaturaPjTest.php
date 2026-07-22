<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusFatura;
use App\Enums\TipoContratante;
use App\Models\Cliente;
use App\Models\Contratante;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Services\Contrato\CriarContratoService;
use App\Services\Elegibilidade\ElegibilidadeService;
use App\Exceptions\DominioException;
use App\Services\Fatura\EmitirCobrancaFaturaPjService;
use App\Services\Fatura\GerarFaturaPjService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaPjTest extends TestCase
{
    use RefreshDatabase;

    private Contratante $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-15'));

        $cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);
        ClienteContext::set($cliente);

        $this->empresa = Contratante::query()->create([
            'chave_sigoweb' => 'EMP-001',
            'tipo' => TipoContratante::Pj,
            'nome' => 'Empresa Teste',
            'documento' => '12345678000199',
        ]);

        Contratante::query()->create([
            'empresa_id' => $this->empresa->id,
            'chave_sigoweb' => 'BEN-PJ-1',
            'tipo' => TipoContratante::Pf,
            'nome' => 'Funcionário 1',
        ]);

        app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-PJ-1',
                'tipo' => 'pf',
                'nome' => 'Funcionário 1',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-PJ',
            'valor_total' => 1200,
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

    public function test_fluxo_fatura_pj_mensal_com_impostos_e_boleto_liquido(): void
    {
        $fatura = app(GerarFaturaPjService::class)->executar(
            $this->empresa,
            '2026-06',
            '2026-06-01',
            ['ir' => 10.00, 'iss' => 5.00]
        );

        $this->assertEquals(100.00, (float) $fatura->valor_bruto);
        $this->assertEquals(15.00, (float) $fatura->valor_retencoes);
        $this->assertEquals(85.00, (float) $fatura->valor_liquido);
        $this->assertCount(1, $fatura->parcelas);
        $this->assertTrue($fatura->lancamentos->contains(fn ($l) => $l->codigo === 'mensalidades' && (float) $l->valor === 100.0));
        $this->assertTrue($fatura->lancamentos->contains(fn ($l) => $l->codigo === 'ir'));

        $cobranca = app(EmitirCobrancaFaturaPjService::class)->executar($fatura);
        $this->assertEquals(85.00, (float) $cobranca->valor);
        $this->assertEquals(StatusFatura::EmCobranca, $fatura->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-06-20'));
        $this->assertFalse(app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('EMP-001')['pode_usar_plano']);
        $this->assertFalse(app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('BEN-PJ-1')['pode_usar_plano']);

        app(LiquidarCobrancaService::class)->executar($cobranca);
        $this->assertEquals(StatusFatura::Paga, $fatura->fresh()->status);
        $this->assertTrue(app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('EMP-001')['pode_usar_plano']);
    }

    public function test_nao_gera_nova_fatura_se_ja_atingiu_max_abertas(): void
    {
        app(GerarFaturaPjService::class)->executar(
            $this->empresa,
            '2026-06',
            '2026-06-01',
            ['ir' => 0, 'iss' => 0]
        );

        $this->expectException(DominioException::class);
        $this->expectExceptionMessage('limite para gerar');

        app(GerarFaturaPjService::class)->executar(
            $this->empresa,
            '2026-07',
            '2026-07-01',
            ['ir' => 0, 'iss' => 0]
        );
    }

    public function test_inadimplencia_pj_respeita_min_faturas_vencidas(): void
    {
        $cliente = ClienteContext::get();
        $cliente->update([
            'config' => array_replace_recursive(ClienteConfig::padraoSerido(), [
                'pj' => [
                    'min_faturas_vencidas_inadimplencia' => 2,
                    'max_faturas_abertas_para_gerar' => 5,
                ],
            ]),
        ]);
        ClienteContext::set($cliente->fresh());

        app(GerarFaturaPjService::class)->executar(
            $this->empresa,
            '2026-06',
            '2026-06-01',
            ['ir' => 0, 'iss' => 0]
        );

        Carbon::setTestNow(Carbon::parse('2026-06-20'));

        // só 1 fatura vencida, mínimo 2 → ainda adimplente
        $eleg = app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('EMP-001');
        $this->assertTrue($eleg['pode_usar_plano']);
        $this->assertEquals(2, $eleg['parametros']['min_faturas_vencidas_inadimplencia']);
    }
}
