<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusParcela;
use App\Models\Cliente;
use App\Models\Parcela;
use App\Services\Contrato\CriarContratoService;
use App\Services\Parcela\BaixarParcelaService;
use App\Services\Parcela\RetirarBaixaParcelaService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Database\Seeders\LocaisPagamentoSeridoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaixarRetirarParcelaTest extends TestCase
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
        ClienteContext::set($this->cliente, [
            'login' => 'operador.lab',
            'nome' => 'Operador Lab',
        ]);

        $this->seed(LocaisPagamentoSeridoSeeder::class);
        ClienteContext::set($this->cliente, [
            'login' => 'operador.lab',
            'nome' => 'Operador Lab',
        ]);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_baixa_e_retira_registrando_operador(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => array_merge([
                'chave_sigoweb' => 'BEN-BAIXA',
                'tipo' => 'pf',
                'nome' => 'Teste Baixa',
            ], $this->enderecoPagadorTeste()),
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-BAIXA',
            'valor_total' => 120,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);

        $parcela = $contrato->parcelas->sortBy('numero')->first();
        $this->assertContains($parcela->status, [StatusParcela::Prevista, StatusParcela::Aberta]);

        $cobranca = app(BaixarParcelaService::class)->executar($parcela->id, [
            'pago_em' => '2026-01-12',
            'local_pagamento_codigo' => '2',
        ]);

        $parcela->refresh();
        $this->assertEquals('paga', $parcela->status->value);
        $this->assertSame('operador.lab', $parcela->baixado_por);
        $this->assertSame('Operador Lab', $parcela->baixado_por_nome);
        $this->assertSame('2', $cobranca->local_pagamento);
        $this->assertSame('operador.lab', $cobranca->baixado_por);

        ClienteContext::set($this->cliente, [
            'login' => 'supervisor.lab',
            'nome' => 'Supervisor Lab',
        ]);

        $cobranca = app(RetirarBaixaParcelaService::class)->executar($parcela->id);
        $parcela->refresh();

        $this->assertEquals('em_cobranca', $parcela->status->value);
        $this->assertNull($parcela->pago_em);
        $this->assertNull($parcela->baixado_por);
        $this->assertSame('supervisor.lab', $parcela->baixa_retirada_por);
        $this->assertSame('Supervisor Lab', $parcela->baixa_retirada_por_nome);
        $this->assertEquals('aberta', $cobranca->status->value);
        $this->assertSame('supervisor.lab', $cobranca->baixa_retirada_por);
    }

    public function test_baixa_cartao_por_codigo_legado(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => array_merge([
                'chave_sigoweb' => 'BEN-CARD',
                'tipo' => 'pf',
                'nome' => 'Teste Card',
            ], $this->enderecoPagadorTeste()),
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-CARD',
            'valor_total' => 100,
            'quantidade_parcelas' => 1,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Imediata->value,
        ]);

        /** @var Parcela $parcela */
        $parcela = $contrato->parcelas->first();

        $cobranca = app(BaixarParcelaService::class)->executar($parcela->id, [
            'codigo_legado' => '61',
        ]);

        $this->assertSame('61', $cobranca->local_pagamento);
        $this->assertEquals(0.95, (float) $cobranca->taxa_percentual);
        $this->assertSame('operador.lab', $cobranca->baixado_por);
    }
}
