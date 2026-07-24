<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Cliente;
use App\Services\Fatura\AlterarEmissaoFaturaService;
use App\Services\Fatura\AlterarVencimentoFaturaService;
use App\Services\Fatura\EmitirCobrancaFaturaPjService;
use App\Services\Fatura\SolicitarFaturaPjService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaPjAlterarDatasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2025-12-10'));

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

    private function criarFatura(): \App\Models\Fatura
    {
        $end = $this->enderecoPagadorTeste();
        $dados = [
            'plano' => array_merge([
                'chave_sigoweb' => 'PLAN-E-DATAS',
                'tipo' => 'E',
                'nome' => 'Plano Datas',
                'razao_social' => 'Empresa Datas LTDA',
                'documento' => '12345678000199',
                'dia_vencimento' => 10,
                'desconto_concedido_percentual' => 0,
                'mes_reajuste' => 1,
                'dt_incl_plano' => '2020-01-01',
            ], $end),
            'competencia' => '2025-11',
            'referencia' => '202511',
            'vidas' => [
                [
                    'federac' => '01',
                    'cooper' => '112',
                    'plano' => 'PLAN-E-DATAS',
                    'familia' => '0001',
                    'depend' => '00',
                    'pessoa' => '1',
                    'nome' => 'Titular',
                    'tipodep' => '3',
                    'tipopag_historico' => '001',
                    'tipopag_mudou_nesta_referencia' => true,
                    'preco' => ['valor' => 100.00],
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

        return app(SolicitarFaturaPjService::class)->executar(
            'PLAN-E-DATAS',
            '2025-11',
            '2025-11-30',
            true,
            null,
            $dados,
        );
    }

    public function test_altera_emissao_para_data_anterior(): void
    {
        $fatura = $this->criarFatura();
        $this->assertEquals('2025-12-10', $fatura->data_emissao->toDateString());

        $fatura = app(AlterarEmissaoFaturaService::class)->executar($fatura, '2025-11-30');

        $this->assertEquals('2025-11-30', $fatura->data_emissao->toDateString());
        $this->assertEquals('2025-11-30', data_get($fatura->meta, 'emissao_alterada.para'));
    }

    public function test_nao_permite_emissao_igual_ou_posterior(): void
    {
        $fatura = $this->criarFatura();

        $this->expectException(DominioException::class);
        app(AlterarEmissaoFaturaService::class)->executar($fatura, '2025-12-10');
    }

    public function test_altera_vencimento_e_cobranca(): void
    {
        $fatura = $this->criarFatura();
        $vencOriginal = $fatura->vencimento->toDateString();
        $cobranca = app(EmitirCobrancaFaturaPjService::class)->executar($fatura->fresh());
        $this->assertEquals(StatusFatura::EmCobranca, $fatura->fresh()->status);
        $this->assertEquals($vencOriginal, $cobranca->vencimento->toDateString());

        $novoVencto = Carbon::parse($vencOriginal)->addDays(15)->toDateString();
        $fatura = app(AlterarVencimentoFaturaService::class)->executar($fatura->fresh(), $novoVencto);

        $this->assertEquals($novoVencto, $fatura->vencimento->toDateString());
        $this->assertEquals($novoVencto, $fatura->cobranca->vencimento->toDateString());
        $this->assertEquals(StatusCobranca::Aberta, $fatura->cobranca->status);
    }
}
