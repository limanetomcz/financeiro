<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusParcela;
use App\Enums\StatusRemessa;
use App\Jobs\GerarRemessaJob;
use App\Models\Cliente;
use App\Models\Parcela;
use App\Services\Bancario\GerarRemessaService;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Contrato\CriarContratoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RemessaSicrediTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-15 10:00:00'));
        Storage::fake('local');

        $this->cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);
        ClienteContext::set($this->cliente);

        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => array_merge([
                'chave_sigoweb' => 'BEN-REM',
                'tipo' => 'pf',
                'nome' => 'Benef Remessa',
                'documento' => '12345678901',
            ], $this->enderecoPagadorTeste()),
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-REM',
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

        // View exige vencimento > hoje; boleto com vencimento futuro.
        app(EmitirCobrancaConsolidadaService::class)->executar(
            [$parcela->id],
            '2026-04-10',
            ['meio' => 'boleto']
        );
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_gera_arquivo_cnab_sicredi_sincrono(): void
    {
        $service = app(GerarRemessaService::class);
        $remessa = $service->enfileirar('2026-01-01', '2026-12-31');
        $remessa = $service->processar($remessa);

        $this->assertSame(StatusRemessa::Concluida, $remessa->status);
        $this->assertGreaterThan(0, $remessa->quantidade_titulos);
        $this->assertNotEmpty($remessa->file_name);
        $this->assertStringEndsWith('.CRM', $remessa->file_name);
        Storage::disk('local')->assertExists($remessa->file_path);

        $conteudo = Storage::disk('local')->get($remessa->file_path);
        $linhas = preg_split("/\r\n|\n/", trim($conteudo));

        $this->assertSame(240, strlen($linhas[0]));
        $this->assertStringStartsWith('74800000', $linhas[0]);
        $this->assertStringStartsWith('74800011', $linhas[1]);
        $this->assertTrue(collect($linhas)->contains(fn ($l) => strlen($l) >= 14 && $l[13] === 'P'));
        $this->assertStringStartsWith('74899999', end($linhas));
    }

    public function test_enfileira_job_na_fila_bancario(): void
    {
        Queue::fake();

        $remessa = app(GerarRemessaService::class)->enfileirar('2026-01-01', '2026-12-31');
        GerarRemessaJob::dispatch($remessa->id);

        Queue::assertPushed(GerarRemessaJob::class, function (GerarRemessaJob $job) use ($remessa) {
            return $job->remessaId === $remessa->id
                && $job->queue === 'bancario';
        });
    }

    public function test_remessa_vazia_quando_sem_titulos(): void
    {
        $service = app(GerarRemessaService::class);
        $remessa = $service->enfileirar('2030-01-01', '2030-01-31');
        $remessa = $service->processar($remessa);

        $this->assertSame(StatusRemessa::Vazia, $remessa->status);
        $this->assertSame(0, $remessa->quantidade_titulos);
    }
}
