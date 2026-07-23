<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Models\Cliente;
use App\Models\LocalPagamento;
use App\Models\TaxaLocalPagamento;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Services\Contrato\CriarContratoService;
use App\Services\LocalPagamento\ResolverLocalPagamentoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Database\Seeders\LocaisPagamentoSeridoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaisPagamentoTaxasTest extends TestCase
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
        ClienteContext::set($this->cliente);

        $this->seed(LocaisPagamentoSeridoSeeder::class);
        ClienteContext::set($this->cliente);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_seeder_serido_separa_canais_e_taxas(): void
    {
        $this->assertSame(8, LocalPagamento::query()->count());
        $this->assertSame(16, TaxaLocalPagamento::query()->count());

        $itau = LocalPagamento::query()->where('codigo', 'ITAU_CARD')->first();
        $this->assertNotNull($itau);
        $this->assertSame(8, $itau->taxas()->count());
        $this->assertTrue($itau->exigeTaxa());
    }

    public function test_resolve_codigo_legado_cartao(): void
    {
        $resolvido = app(ResolverLocalPagamentoService::class)->porCodigoLegado('61');

        $this->assertSame('ITAU_CARD', $resolvido['local']->codigo);
        $this->assertSame('61', $resolvido['taxa']->codigo_legado);
        $this->assertEquals(0.95, (float) $resolvido['taxa']->taxa_percentual);
    }

    public function test_resolve_codigo_legado_banco(): void
    {
        $resolvido = app(ResolverLocalPagamentoService::class)->porCodigoLegado('7');

        $this->assertSame('7', $resolvido['local']->codigo);
        $this->assertNull($resolvido['taxa']);
    }

    public function test_liquidar_com_codigo_legado_grava_snapshot(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => array_merge([
                'chave_sigoweb' => 'BEN-TAXA',
                'tipo' => 'pf',
                'nome' => 'Teste Taxa',
            ], $this->enderecoPagadorTeste()),
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-TAXA',
            'valor_total' => 100,
            'quantidade_parcelas' => 1,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Imediata->value,
        ]);

        $parcelaId = $contrato->parcelas->first()->id;
        $cobranca = app(EmitirCobrancaConsolidadaService::class)->executar(
            [$parcelaId],
            '2026-01-10',
            ['meio' => 'cartao']
        );

        $paga = app(LiquidarCobrancaService::class)->executar($cobranca, '2026-01-12', [
            'codigo_legado' => '61',
        ]);

        $this->assertEquals('paga', $paga->status->value);
        $this->assertSame('61', $paga->local_pagamento);
        $this->assertSame('ITAU - DEBITO MASTER/VISA', $paga->local_pagamento_descricao);
        $this->assertEquals(0.95, (float) $paga->taxa_percentual);
        $this->assertEquals(0.95, (float) $paga->valor_taxa); // 100 * 0.95%
        $this->assertSame('debito', $paga->modalidade);
        $this->assertSame('master_visa', $paga->bandeira);
        $this->assertSame('cartao', $paga->meio);
    }
}
