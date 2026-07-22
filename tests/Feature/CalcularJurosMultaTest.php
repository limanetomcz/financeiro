<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Models\Cliente;
use App\Services\Contrato\CriarContratoService;
use App\Services\Parcela\BaixarParcelaService;
use App\Services\Parcela\CalcularJurosMultaService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Database\Seeders\LocaisPagamentoSeridoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalcularJurosMultaTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        $this->cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);
        ClienteContext::set($this->cliente, ['login' => 'op.juros', 'nome' => 'Op Juros']);
        $this->seed(LocaisPagamentoSeridoSeeder::class);
        ClienteContext::set($this->cliente, ['login' => 'op.juros', 'nome' => 'Op Juros']);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_formula_oracle_033_ao_dia_mais_multa_2(): void
    {
        // valor 100, 30 dias: round(0.033*30*100)=round(99)=99 → juros 0.99; multa 2.00; total 102.99
        $r = app(CalcularJurosMultaService::class)->calcular(100, '2026-01-10', '2026-02-09');

        $this->assertTrue($r['atrasada']);
        $this->assertSame(30, $r['dias_atraso']);
        $this->assertEquals(2.0, $r['valor_multa']);
        $this->assertEquals(0.99, $r['valor_juros']);
        $this->assertEquals(102.99, $r['valor_total']);
        $this->assertFalse($r['carencia_fds_aplicada']);
    }

    public function test_sem_atraso_zerado(): void
    {
        $r = app(CalcularJurosMultaService::class)->calcular(100, '2026-03-10', '2026-03-10');

        $this->assertFalse($r['atrasada']);
        $this->assertSame(0, $r['dias_atraso']);
        $this->assertEquals(0.0, $r['valor_juros']);
        $this->assertEquals(0.0, $r['valor_multa']);
        $this->assertEquals(100.0, $r['valor_total']);
    }

    public function test_carencia_fds_vencimento_sabado(): void
    {
        // 2026-01-10 é sábado; pagamento domingo 11/01 (1 dia, dentro da carência)
        $r = app(CalcularJurosMultaService::class)->calcular(100, '2026-01-10', '2026-01-11');

        $this->assertTrue($r['atrasada']);
        $this->assertTrue($r['carencia_fds_aplicada']);
        $this->assertEquals(0.0, $r['valor_juros']);
        $this->assertEquals(0.0, $r['valor_multa']);
        $this->assertEquals(100.0, $r['valor_total']);
    }

    public function test_baixa_aplica_encargos_na_cobranca(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-JUROS',
                'tipo' => 'pf',
                'nome' => 'Teste Juros',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-JUROS',
            'valor_total' => 100,
            'quantidade_parcelas' => 1,
            'primeiro_vencimento' => '2026-01-12', // segunda — sem carência FDS
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Imediata->value,
        ]);

        $parcela = $contrato->parcelas->first();

        // 28 dias (12/01 → 09/02): round(0.033*28*100)=round(92.4)=92 → 0.92 + 2 + 100 = 102.92
        $cobranca = app(BaixarParcelaService::class)->executar($parcela->id, [
            'pago_em' => '2026-02-09',
            'local_pagamento_codigo' => '2',
            'aplicar_encargos' => true,
        ]);

        $this->assertEquals(2.0, (float) $cobranca->valor_multa);
        $this->assertEquals(0.92, (float) $cobranca->valor_juros);
        $this->assertEquals(102.92, (float) $cobranca->valor);
        $this->assertEquals('paga', $cobranca->status->value);
    }

    public function test_baixa_pode_dispensar_encargos(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-DISP',
                'tipo' => 'pf',
                'nome' => 'Teste Disp',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-DISP',
            'valor_total' => 100,
            'quantidade_parcelas' => 1,
            'primeiro_vencimento' => '2026-01-12',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Imediata->value,
        ]);

        $cobranca = app(BaixarParcelaService::class)->executar($contrato->parcelas->first()->id, [
            'pago_em' => '2026-02-09',
            'local_pagamento_codigo' => '2',
            'aplicar_encargos' => false,
        ]);

        $this->assertEquals(0.0, (float) $cobranca->valor_juros);
        $this->assertEquals(0.0, (float) $cobranca->valor_multa);
        $this->assertEquals(100.0, (float) $cobranca->valor);
    }
}
