<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Models\Cliente;
use App\Services\Fatura\EmitirCobrancaFaturaPjService;
use App\Services\Fatura\GerarPdfDemonstrativoFaturaService;
use App\Services\Fatura\GerarPdfFaturaPjService;
use App\Services\Fatura\SolicitarFaturaPjService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaPjPdfTest extends TestCase
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

    public function test_gera_fatura_e_dois_demonstrativos_e_boleto(): void
    {
        $end = $this->enderecoPagadorTeste();
        $dados = [
            'plano' => array_merge([
                'chave_sigoweb' => 'PLAN-E-PDF',
                'tipo' => 'E',
                'nome' => 'Plano PDF',
                'razao_social' => 'Empresa PDF LTDA',
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
                    'plano' => 'PLAN-E-PDF',
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
                    'plano' => 'PLAN-E-PDF',
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
            'PLAN-E-PDF',
            '2026-06',
            null,
            true,
            null,
            $dados,
        );

        $this->assertEquals(StatusFatura::Aberta, $fatura->status);

        $vidas = $fatura->lancamentos->where('codigo', 'mensalidade');
        $this->assertCount(2, $vidas);
        $this->assertEquals('3', data_get($vidas->firstWhere('descricao', 'Titular Um')?->meta, 'tipodep'));
        $this->assertEquals('1', data_get($vidas->firstWhere('descricao', 'Dependente')?->meta, 'tipodep'));

        $pdfFatura = app(GerarPdfFaturaPjService::class)->executar($fatura);
        $this->assertStringStartsWith('%PDF', $pdfFatura);

        $pdfTitulares = app(GerarPdfDemonstrativoFaturaService::class)->executar($fatura, false);
        $this->assertStringStartsWith('%PDF', $pdfTitulares);

        $pdfCompleto = app(GerarPdfDemonstrativoFaturaService::class)->executar($fatura, true);
        $this->assertStringStartsWith('%PDF', $pdfCompleto);

        $cobranca = app(EmitirCobrancaFaturaPjService::class)->executar($fatura->fresh());
        $pdfBoleto = app(\App\Services\Boleto\GerarPdfBoletoService::class)->executar($cobranca);
        $this->assertStringStartsWith('%PDF', $pdfBoleto);
    }
}
