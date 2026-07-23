<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Services\Elegibilidade\ElegibilidadeService;
use App\Services\Fatura\CalcularImpostosFaturaPjService;
use App\Services\Fatura\EmitirCobrancaFaturaPjService;
use App\Services\Fatura\GerarFaturaPjService;
use App\Models\Cliente;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaPjPlanoTest extends TestCase
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

    public function test_calcula_iss_e_irrf_conforme_flags_do_plano(): void
    {
        $lanc = app(CalcularImpostosFaturaPjService::class)->executar([
            'valor_bruto' => 1000,
            'flags' => [
                'irrf' => true,
                'iss' => true,
                'piscofins' => false,
                'csll' => false,
                'inss' => false,
            ],
            'aliquotas' => [
                'irrf' => 1.5,
                'iss' => 5,
                'piscofins' => 0,
                'csll' => 0,
                'inss' => 0,
            ],
            'regras' => ['irrf_minimo' => 10],
        ]);

        $this->assertTrue(collect($lanc)->contains(fn ($l) => $l['codigo'] === 'iss' && $l['valor'] === 50.0));
        $this->assertTrue(collect($lanc)->contains(fn ($l) => $l['codigo'] === 'ir' && $l['valor'] === 15.0));
    }

    public function test_nao_calcula_iss_se_flag_desligada(): void
    {
        $lanc = app(CalcularImpostosFaturaPjService::class)->executar([
            'valor_bruto' => 1000,
            'flags' => ['irrf' => false, 'iss' => false, 'piscofins' => false, 'csll' => false, 'inss' => false],
            'aliquotas' => ['irrf' => 1.5, 'iss' => 5],
        ]);

        $this->assertSame([], $lanc);
    }

    public function test_gera_fatura_por_composicao_do_plano(): void
    {
        $end = $this->enderecoPagadorTeste();
        $composicao = [
            'plano' => array_merge([
                'chave_sigoweb' => 'EMP-PLAN-01',
                'tipo' => 'E',
                'nome' => 'Plano Empresa Lab',
                'razao_social' => 'Empresa Lab LTDA',
                'documento' => '12345678000199',
                'dia_vencimento' => 10,
            ], $end),
            'competencia' => '2026-06',
            'referencia' => '202606',
            'base' => [
                'valor_mensalidades' => 1000.00,
                'valor_custo' => 0,
                'desconto_concedido_percentual' => 0,
                'desconto_concedido_valor' => 0,
                'valor_bruto' => 1000.00,
                'mensalidades_qtd' => 12,
                'vidas_qtd' => 12,
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
                'regras' => [
                    'irrf_minimo' => 10,
                    'piscofins_csll_bruto_minimo' => 5000,
                    'inss_base_percentual' => 60,
                ],
            ],
        ];

        $fatura = app(GerarFaturaPjService::class)->executarPorPlano(
            'EMP-PLAN-01',
            '2026-06',
            null,
            $composicao
        );

        $this->assertEquals(1000.00, (float) $fatura->valor_bruto);
        $this->assertEquals(65.00, (float) $fatura->valor_retencoes); // 15 IR + 50 ISS
        $this->assertEquals(935.00, (float) $fatura->valor_liquido);
        $this->assertEquals('EMP-PLAN-01', $fatura->contratante->chave_sigoweb);

        $cobranca = app(EmitirCobrancaFaturaPjService::class)->executar($fatura);
        $this->assertEquals(935.00, (float) $cobranca->valor);
        $this->assertEquals(StatusFatura::EmCobranca, $fatura->fresh()->status);

        app(LiquidarCobrancaService::class)->executar($cobranca);
        $this->assertEquals(StatusFatura::Paga, $fatura->fresh()->status);
        $this->assertTrue(app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('EMP-PLAN-01')['pode_usar_plano']);
    }
}
