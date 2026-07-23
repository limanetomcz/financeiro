<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Models\Cliente;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Services\Elegibilidade\ElegibilidadeService;
use App\Services\Fatura\CalcularValorVidaSeridoService;
use App\Services\Fatura\EmitirCobrancaFaturaPjService;
use App\Services\Fatura\SolicitarFaturaPjService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaPjAsyncTest extends TestCase
{
    use RefreshDatabase;

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
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_primeira_vida_usa_preco_tabela(): void
    {
        $valor = app(CalcularValorVidaSeridoService::class)->executar(
            [
                'preco' => ['valor' => 120.50],
                'tipopag_mudou_nesta_referencia' => false,
            ],
            null,
            '2026-06',
        );

        $this->assertEquals(120.50, $valor);
    }

    public function test_mes_seguinte_carrega_valor_anterior_do_financeiro(): void
    {
        $valor = app(CalcularValorVidaSeridoService::class)->executar(
            [
                'preco' => ['valor' => 999],
                'tipopag_mudou_nesta_referencia' => false,
            ],
            120.50,
            '2026-07',
        );

        $this->assertEquals(120.50, $valor);
    }

    public function test_fluxo_async_sincrono_com_dados_override(): void
    {
        $end = $this->enderecoPagadorTeste();
        $dados = [
            'plano' => array_merge([
                'chave_sigoweb' => 'PLAN-E-01',
                'tipo' => 'E',
                'nome' => 'Plano E Lab',
                'razao_social' => 'Empresa Lab LTDA',
                'documento' => '12345678000199',
                'dia_vencimento' => 10,
                'desconto_concedido_percentual' => 0,
                'mes_reajuste' => 1,
                'dt_incl_plano' => '2020-01-01',
            ], $end),
            'competencia' => '2026-06',
            'referencia' => '202606',
            'vidas' => [
                [
                    'federac' => '01',
                    'cooper' => '112',
                    'plano' => 'PLAN-E-01',
                    'familia' => '0001',
                    'depend' => '00',
                    'pessoa' => '1',
                    'nome' => 'Titular Um',
                    'tipodep' => '3',
                    'tipopag_historico' => '001',
                    'tipopag_mudou_nesta_referencia' => true,
                    'preco' => ['valor' => 100.00],
                ],
                [
                    'federac' => '01',
                    'cooper' => '112',
                    'plano' => 'PLAN-E-01',
                    'familia' => '0001',
                    'depend' => '01',
                    'pessoa' => '2',
                    'nome' => 'Dependente',
                    'tipodep' => '1',
                    'tipopag_historico' => '001',
                    'tipopag_mudou_nesta_referencia' => true,
                    'preco' => ['valor' => 50.00],
                ],
            ],
            'impostos' => [
                'flags' => [
                    'irrf' => true,
                    'iss' => true,
                    'piscofins' => false,
                    'csll' => false,
                    'inss' => false,
                ],
                'aliquotas' => [
                    'irrf' => 1.5,
                    'iss' => 5.0,
                    'piscofins' => 0,
                    'csll' => 0,
                    'inss' => 0,
                ],
                'regras' => ['irrf_minimo' => 10],
            ],
        ];

        $fatura = app(SolicitarFaturaPjService::class)->executar(
            'PLAN-E-01',
            '2026-06',
            null,
            true,
            null,
            $dados,
        );

        $this->assertEquals(StatusFatura::Aberta, $fatura->status);
        $this->assertEquals(150.00, (float) $fatura->valor_bruto);
        $this->assertMatchesRegularExpression('/^\d{6}\/\d{4}$/', (string) $fatura->numero);
        $this->assertStringStartsWith('202606/', (string) $fatura->numero);
        // IR 2.25 < 10 → 0; ISS 7.50
        $this->assertEquals(7.50, (float) $fatura->valor_retencoes);
        $this->assertEquals(142.50, (float) $fatura->valor_liquido);
        $this->assertCount(3, $fatura->lancamentos); // 2 vidas + iss

        $cobranca = app(EmitirCobrancaFaturaPjService::class)->executar($fatura);
        $this->assertEquals(142.50, (float) $cobranca->valor);

        app(LiquidarCobrancaService::class)->executar($cobranca);
        $this->assertTrue(app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('PLAN-E-01')['pode_usar_plano']);
    }

    public function test_remover_fatura_com_cobranca_aberta(): void
    {
        $end = $this->enderecoPagadorTeste();
        $dados = [
            'plano' => array_merge([
                'chave_sigoweb' => 'PLAN-E-DEL',
                'tipo' => 'E',
                'nome' => 'Plano Del',
                'razao_social' => 'Empresa Del LTDA',
                'documento' => '12345678000199',
                'dia_vencimento' => 10,
                'desconto_concedido_percentual' => 0,
                'mes_reajuste' => 1,
                'dt_incl_plano' => '2020-01-01',
            ], $end),
            'competencia' => '2026-06',
            'referencia' => '202606',
            'vidas' => [
                [
                    'federac' => '01',
                    'cooper' => '112',
                    'plano' => 'PLAN-E-DEL',
                    'familia' => '0001',
                    'depend' => '00',
                    'pessoa' => '1',
                    'nome' => 'Titular',
                    'tipodep' => '3',
                    'tipopag_historico' => '001',
                    'tipopag_mudou_nesta_referencia' => true,
                    'preco' => ['valor' => 80.00],
                ],
            ],
            'impostos' => [
                'flags' => [
                    'irrf' => false,
                    'iss' => false,
                    'piscofins' => false,
                    'csll' => false,
                    'inss' => false,
                ],
                'aliquotas' => [
                    'irrf' => 0,
                    'iss' => 0,
                    'piscofins' => 0,
                    'csll' => 0,
                    'inss' => 0,
                ],
                'regras' => ['irrf_minimo' => 10],
            ],
        ];

        $fatura = app(SolicitarFaturaPjService::class)->executar(
            'PLAN-E-DEL',
            '2026-06',
            null,
            true,
            null,
            $dados,
        );
        $cobranca = app(EmitirCobrancaFaturaPjService::class)->executar($fatura);
        $faturaId = $fatura->id;
        $cobrancaId = $cobranca->id;

        $resultado = app(\App\Services\Fatura\RemoverFaturaService::class)->executar($fatura->fresh());

        $this->assertEquals(1, $resultado['apagados']['fatura']);
        $this->assertEquals(1, $resultado['apagados']['cobranca_cancelada']);
        $this->assertNull(\App\Models\Fatura::query()->find($faturaId));
        $this->assertNotNull(\App\Models\Fatura::withTrashed()->find($faturaId));
        $this->assertNotNull(\App\Models\Fatura::withTrashed()->find($faturaId)?->deleted_at);
        $this->assertEquals('202606/0001', \App\Models\Fatura::withTrashed()->find($faturaId)?->numero);
        $this->assertNull(\App\Models\Cobranca::query()->find($cobrancaId));
        $cobranca = \App\Models\Cobranca::withTrashed()->find($cobrancaId);
        $this->assertNotNull($cobranca);
        $this->assertNotNull($cobranca->deleted_at);
        $this->assertEquals(\App\Enums\StatusCobranca::Cancelada, $cobranca->status);

        // Sequência queimada: próxima fatura da mesma competência sobe o SSSS.
        $fatura2 = app(SolicitarFaturaPjService::class)->executar(
            'PLAN-E-DEL',
            '2026-06',
            null,
            true,
            null,
            $dados,
        );
        $this->assertEquals('202606/0002', $fatura2->numero);
    }

    public function test_nao_remove_fatura_paga(): void
    {
        $end = $this->enderecoPagadorTeste();
        $dados = [
            'plano' => array_merge([
                'chave_sigoweb' => 'PLAN-E-PAY',
                'tipo' => 'E',
                'nome' => 'Plano Pay',
                'razao_social' => 'Empresa Pay LTDA',
                'documento' => '12345678000199',
                'dia_vencimento' => 10,
                'desconto_concedido_percentual' => 0,
                'mes_reajuste' => 1,
                'dt_incl_plano' => '2020-01-01',
            ], $end),
            'competencia' => '2026-06',
            'referencia' => '202606',
            'vidas' => [
                [
                    'federac' => '01',
                    'cooper' => '112',
                    'plano' => 'PLAN-E-PAY',
                    'familia' => '0001',
                    'depend' => '00',
                    'pessoa' => '1',
                    'nome' => 'Titular',
                    'tipodep' => '3',
                    'tipopag_historico' => '001',
                    'tipopag_mudou_nesta_referencia' => true,
                    'preco' => ['valor' => 90.00],
                ],
            ],
            'impostos' => [
                'flags' => [
                    'irrf' => false,
                    'iss' => false,
                    'piscofins' => false,
                    'csll' => false,
                    'inss' => false,
                ],
                'aliquotas' => [
                    'irrf' => 0,
                    'iss' => 0,
                    'piscofins' => 0,
                    'csll' => 0,
                    'inss' => 0,
                ],
                'regras' => ['irrf_minimo' => 10],
            ],
        ];

        $fatura = app(SolicitarFaturaPjService::class)->executar(
            'PLAN-E-PAY',
            '2026-06',
            null,
            true,
            null,
            $dados,
        );
        $cobranca = app(EmitirCobrancaFaturaPjService::class)->executar($fatura);
        app(LiquidarCobrancaService::class)->executar($cobranca);

        $this->expectException(\App\Exceptions\DominioException::class);
        app(\App\Services\Fatura\RemoverFaturaService::class)->executar($fatura->fresh());
    }
}
