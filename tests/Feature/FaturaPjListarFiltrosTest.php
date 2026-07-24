<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Models\Cliente;
use App\Services\Fatura\ListarFaturasService;
use App\Services\Fatura\RemoverFaturaService;
use App\Services\Fatura\SolicitarFaturaPjService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaturaPjListarFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-15'));

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

    private function dadosPlano(string $chave, string $comp): array
    {
        $end = $this->enderecoPagadorTeste();

        return [
            'plano' => array_merge([
                'chave_sigoweb' => $chave,
                'tipo' => 'E',
                'nome' => 'Plano '.$chave,
                'razao_social' => 'Empresa '.$chave,
                'documento' => '12345678000199',
                'dia_vencimento' => 10,
                'desconto_concedido_percentual' => 0,
                'mes_reajuste' => 1,
                'dt_incl_plano' => '2020-01-01',
            ], $end),
            'competencia' => $comp,
            'referencia' => str_replace('-', '', $comp),
            'vidas' => [
                [
                    'federac' => '01',
                    'cooper' => '112',
                    'plano' => $chave,
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
    }

    public function test_filtra_por_numero_status_e_apenas_abertas(): void
    {
        $a = app(SolicitarFaturaPjService::class)->executar(
            'PLAN-FILTRO-A',
            '2026-06',
            null,
            true,
            null,
            $this->dadosPlano('PLAN-FILTRO-A', '2026-06'),
        );
        $b = app(SolicitarFaturaPjService::class)->executar(
            'PLAN-FILTRO-B',
            '2026-07',
            null,
            true,
            null,
            $this->dadosPlano('PLAN-FILTRO-B', '2026-07'),
        );

        app(RemoverFaturaService::class)->executar($b->fresh());

        $porNumero = app(ListarFaturasService::class)->executar(['numero' => $a->numero]);
        $this->assertSame(1, $porNumero->total());
        $this->assertSame($a->id, $porNumero->items()[0]['id']);

        $abertas = app(ListarFaturasService::class)->executar(['apenas_abertas' => true]);
        $this->assertSame(1, $abertas->total());

        $comExcluidas = app(ListarFaturasService::class)->executar([
            'incluir_excluidas' => true,
            'chave_plano_sigoweb' => 'PLAN-FILTRO-B',
        ]);
        $this->assertSame(1, $comExcluidas->total());
        $this->assertTrue($comExcluidas->items()[0]['excluida']);

        $porEmissao = app(ListarFaturasService::class)->executar([
            'data_emissao_de' => '2026-07-15',
            'data_emissao_ate' => '2026-07-15',
            'status' => StatusFatura::Aberta->value,
        ]);
        $this->assertSame(1, $porEmissao->total());
    }
}
