<?php

namespace Tests\Feature;

use App\Enums\StatusParcela;
use App\Models\Cliente;
use App\Models\Parcela;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Services\Contrato\CriarContratoService;
use App\Services\Elegibilidade\ElegibilidadeService;
use App\Support\Tenant\ClienteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DominioCobrancaTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'usa_financeiro_novo' => false,
        ]);

        ClienteContext::set($this->cliente);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        parent::tearDown();
    }

    public function test_cria_contrato_com_parcelas_rateadas(): void
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
            'valor_total' => 100.00,
            'quantidade_parcelas' => 3,
            'primeiro_vencimento' => '2026-01-10',
        ]);

        $this->assertCount(3, $contrato->parcelas);
        $this->assertEquals('33.34', $contrato->parcelas[0]->valor);
        $this->assertEquals('33.33', $contrato->parcelas[1]->valor);
        $this->assertEquals('33.33', $contrato->parcelas[2]->valor);
        $this->assertEquals(100.00, (float) $contrato->parcelas->sum('valor'));
    }

    public function test_consolidada_e_liquidacao_baixam_parcelas(): void
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
        ]);

        $ids = $contrato->parcelas->pluck('id')->all();

        $cobranca = app(EmitirCobrancaConsolidadaService::class)->executar($ids, '2026-03-15', 'manual');
        $this->assertEquals('consolidada', $cobranca->tipo->value);
        $this->assertEquals(120.00, (float) $cobranca->valor);

        foreach (Parcela::query()->whereIn('id', $ids)->get() as $parcela) {
            $this->assertEquals(StatusParcela::EmCobranca, $parcela->status);
        }

        $cobranca = app(LiquidarCobrancaService::class)->executar($cobranca);

        $this->assertEquals('paga', $cobranca->status->value);
        foreach (Parcela::query()->whereIn('id', $ids)->get() as $parcela) {
            $this->assertEquals(StatusParcela::Paga, $parcela->status);
            $this->assertNotNull($parcela->pago_em);
        }
    }

    public function test_elegibilidade_bloqueia_parcela_vencida(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
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
        ]);

        $resultado = app(ElegibilidadeService::class)->avaliarPorChaveSigoweb('BEN-003');

        $this->assertFalse($resultado['pode_usar_plano']);
        $this->assertGreaterThan(0, $resultado['parcelas_vencidas']);
        $this->assertNotNull($contrato->id);
    }
}
