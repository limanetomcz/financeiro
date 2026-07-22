<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusParcela;
use App\Enums\StatusRemessa;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Parcela;
use App\Models\Remessa;
use App\Services\Bancario\GerarRemessaService;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Contrato\CriarContratoService;
use App\Services\Lab\ApagarRemessaLabService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LabApagarRemessaTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private Remessa $remessa;

    private string $parcelaId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-15 10:00:00'));
        Storage::fake('local');
        config(['financeiro.lab_limpeza_habilitada' => true]);

        $this->cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);
        ClienteContext::set($this->cliente);

        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-APAGAR-REM',
                'tipo' => 'pf',
                'nome' => 'Benef Apagar Remessa',
                'documento' => '12345678901',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-APAGAR',
            'valor_total' => 120,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);

        $parcela = Parcela::query()
            ->where('contrato_id', $contrato->id)
            ->where('status', StatusParcela::Aberta)
            ->orderBy('vencimento')
            ->firstOrFail();

        $this->parcelaId = $parcela->id;

        app(EmitirCobrancaConsolidadaService::class)->executar(
            [$parcela->id],
            '2026-04-10',
            ['meio' => 'boleto']
        );

        $service = app(GerarRemessaService::class);
        $this->remessa = $service->processar($service->enfileirar('2026-01-01', '2026-12-31'));
        $this->assertSame(StatusRemessa::Concluida, $this->remessa->status);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_apaga_remessa_boletos_e_reseta_parcelas(): void
    {
        $this->assertSame(StatusParcela::EmCobranca, Parcela::query()->find($this->parcelaId)->status);
        $this->assertGreaterThan(0, Cobranca::query()->count());

        $resultado = app(ApagarRemessaLabService::class)->executar($this->remessa->id);

        $this->assertSame(1, $resultado['apagados']['remessa']);
        $this->assertGreaterThan(0, $resultado['apagados']['cobrancas']);
        $this->assertNull(Remessa::query()->find($this->remessa->id));
        $this->assertSame(0, Cobranca::query()->count());
        $this->assertSame(StatusParcela::Aberta, Parcela::query()->find($this->parcelaId)->status);
    }

    public function test_bloqueia_quando_desabilitado(): void
    {
        config(['financeiro.lab_limpeza_habilitada' => false]);

        $this->expectException(\App\Exceptions\DominioException::class);

        app(ApagarRemessaLabService::class)->executar($this->remessa->id);
    }
}
