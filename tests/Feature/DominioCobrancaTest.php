<?php

namespace Tests\Feature;

use App\Enums\StatusParcela;
use App\Models\Cliente;
use App\Models\Parcela;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Services\Contrato\CriarContratoService;
use App\Services\Elegibilidade\ElegibilidadeService;
use App\Services\Parcela\AbrirParcelasExigiveisService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DominioCobrancaTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-21'));

        $this->cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'usa_financeiro_novo' => false,
            'config' => ClienteConfig::padraoSerido(),
        ]);

        ClienteContext::set($this->cliente);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cria_contrato_com_parcelas_rateadas_e_previstas(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-001',
                'tipo' => 'pf',
                'nome' => 'Fulano',
                'documento' => '12345678901',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 120.00,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
        ]);

        $this->assertCount(12, $contrato->parcelas);
        $this->assertEquals(120.00, (float) $contrato->parcelas->sum('valor'));

        $abertas = $contrato->parcelas->where('status', StatusParcela::Aberta)->count();
        $previstas = $contrato->parcelas->where('status', StatusParcela::Prevista)->count();

        // jan..jul/2026 exigíveis; ago..dez previstas
        $this->assertEquals(7, $abertas);
        $this->assertEquals(5, $previstas);
        $this->assertEquals('pf', $contrato->tipo);
    }

    public function test_abrir_parcelas_exigiveis_promove_previstas(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-010',
                'tipo' => 'pf',
                'nome' => 'Teste',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 120.00,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-05'));
        $resultado = app(AbrirParcelasExigiveisService::class)->executar();

        $this->assertEquals(1, $resultado['abertas']);
        $this->assertEquals(
            8,
            Parcela::query()->where('contrato_id', $contrato->id)->where('status', StatusParcela::Aberta)->count()
        );
    }

    public function test_consolidada_com_juros_e_multa_e_liquidacao(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-002',
                'tipo' => 'pf',
                'nome' => 'Beltrano',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'valor_total' => 120.00,
            'quantidade_parcelas' => 3,
            'primeiro_vencimento' => '2026-01-10',
            'modo_geracao' => ClienteConfig::MODO_TODAS_ABERTAS,
        ]);

        $ids = $contrato->parcelas->pluck('id')->all();

        $cobranca = app(EmitirCobrancaConsolidadaService::class)->executar($ids, '2026-03-15', [
            'meio' => 'boleto',
            'valor_juros' => 5.50,
            'valor_multa' => 2.00,
        ]);

        $this->assertEquals('consolidada', $cobranca->tipo->value);
        $this->assertEquals(120.00, (float) $cobranca->valor_principal);
        $this->assertEquals(5.50, (float) $cobranca->valor_juros);
        $this->assertEquals(2.00, (float) $cobranca->valor_multa);
        $this->assertEquals(127.50, (float) $cobranca->valor);

        $cobranca = app(LiquidarCobrancaService::class)->executar($cobranca);
        $this->assertEquals('paga', $cobranca->status->value);
    }

    public function test_elegibilidade_respeita_min_parcelas_parametrizado(): void
    {
        $this->cliente->update([
            'config' => array_replace_recursive(ClienteConfig::padraoSerido(), [
                'elegibilidade' => [
                    'dias_apos_vencimento' => 0,
                    'min_parcelas_vencidas' => 2,
                ],
            ]),
        ]);
        ClienteContext::set($this->cliente->fresh());

        app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-003',
                'tipo' => 'pf',
                'nome' => 'Ciclano',
            ],
            'vigencia_inicio' => '2025-01-01',
            'vigencia_fim' => '2025-12-31',
            'valor_total' => 50.00,
            'quantidade_parcelas' => 1,
            'primeiro_vencimento' => '2025-01-10',
            'modo_geracao' => ClienteConfig::MODO_TODAS_ABERTAS,
        ]);

        // só 1 vencida, mínimo 2 → ainda pode usar
        $resultado = app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('BEN-003');
        $this->assertTrue($resultado['pode_usar_plano']);
        $this->assertEquals(1, $resultado['parcelas_vencidas']);

        $resultado = app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('BEN-003', null, 1);
        $this->assertFalse($resultado['pode_usar_plano']);
    }
}
