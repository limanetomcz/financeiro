<?php

namespace Tests\Feature;

use App\Enums\AcaoRetornoItem;
use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusCobranca;
use App\Enums\StatusParcela;
use App\Enums\StatusRemessa;
use App\Enums\StatusRetornoBancario;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Parcela;
use App\Models\RemessaItem;
use App\Services\Bancario\GerarRemessaService;
use App\Services\Bancario\ProcessarRetornoBancarioService;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Contrato\CriarContratoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Database\Seeders\LocaisPagamentoSeridoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SicrediRetornoCnab240Fixture;
use Tests\TestCase;

class RetornoBancarioSicrediTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private Cobranca $cobranca;

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
        ClienteContext::set($this->cliente, [
            'login' => 'operador.retorno',
            'nome' => 'Operador Retorno',
        ]);
        $this->seed(LocaisPagamentoSeridoSeeder::class);
        ClienteContext::set($this->cliente, [
            'login' => 'operador.retorno',
            'nome' => 'Operador Retorno',
        ]);

        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-RET',
                'tipo' => 'pf',
                'nome' => 'Benef Retorno',
                'documento' => '12345678901',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-RET',
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

        $this->cobranca = app(EmitirCobrancaConsolidadaService::class)->executar(
            [$parcela->id],
            '2026-04-10',
            ['meio' => 'boleto']
        );

        $remessa = app(GerarRemessaService::class)->enfileirar('2026-01-01', '2026-12-31');
        $remessa = app(GerarRemessaService::class)->processar($remessa);
        $this->assertSame(StatusRemessa::Concluida, $remessa->status);

        ClienteContext::set($this->cliente, [
            'login' => 'operador.retorno',
            'nome' => 'Operador Retorno',
        ]);

        $this->cobranca->refresh();
        $this->assertNotEmpty($this->cobranca->nosso_numero);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_confirma_entrada_atualiza_enviado_remessa(): void
    {
        $conteudo = SicrediRetornoCnab240Fixture::arquivoComOcorrencias([
            [
                'codigo' => '02',
                'nosso_numero' => $this->cobranca->nosso_numero,
                'numero_registro' => $this->cobranca->numero_registro,
                'com_u' => false,
            ],
        ]);

        $retorno = app(ProcessarRetornoBancarioService::class)->executar($conteudo, 'CONF.CRT');

        $this->assertSame(StatusRetornoBancario::Concluido, $retorno->status);
        $this->assertSame(1, $retorno->quantidade_confirmadas);
        $this->assertSame(AcaoRetornoItem::ConfirmarEntrada, $retorno->itens->first()->acao);

        $item = RemessaItem::query()->where('cobranca_id', $this->cobranca->id)->firstOrFail();
        $this->assertSame(2, (int) $item->enviado_remessa);
    }

    public function test_liquida_cobranca_com_codigo_06(): void
    {
        RemessaItem::query()
            ->where('cobranca_id', $this->cobranca->id)
            ->update(['enviado_remessa' => 2]);

        $conteudo = SicrediRetornoCnab240Fixture::arquivoComOcorrencias([
            [
                'codigo' => '06',
                'nosso_numero' => $this->cobranca->nosso_numero,
                'numero_registro' => $this->cobranca->numero_registro,
                'valor_pago' => (float) $this->cobranca->valor,
                'pago_em' => '15032026',
            ],
        ]);

        $retorno = app(ProcessarRetornoBancarioService::class)->executar($conteudo, 'LIQ.CRT');

        $this->assertSame(StatusRetornoBancario::Concluido, $retorno->status);
        $this->assertSame(1, $retorno->quantidade_liquidadas);

        $this->cobranca->refresh();
        $this->assertSame(StatusCobranca::Paga, $this->cobranca->status);
        $this->assertSame('7', $this->cobranca->local_pagamento);
        $this->assertSame('2026-03-15', $this->cobranca->pago_em?->toDateString());
        $this->assertSame('operador.retorno', $this->cobranca->baixado_por);
    }

    public function test_rejeita_arquivo_duplicado(): void
    {
        $conteudo = SicrediRetornoCnab240Fixture::arquivoComOcorrencias([
            [
                'codigo' => '02',
                'nosso_numero' => $this->cobranca->nosso_numero,
                'com_u' => false,
            ],
        ]);

        app(ProcessarRetornoBancarioService::class)->executar($conteudo, 'DUP1.CRT');

        $this->expectExceptionMessage('já foi processado');
        app(ProcessarRetornoBancarioService::class)->executar($conteudo, 'DUP2.CRT');
    }

    public function test_marca_erro_quando_nosso_numero_desconhecido(): void
    {
        $conteudo = SicrediRetornoCnab240Fixture::arquivoComOcorrencias([
            [
                'codigo' => '06',
                'nosso_numero' => 'ZZZINEXISTENTE',
                'numero_registro' => '999999999',
            ],
        ]);

        $retorno = app(ProcessarRetornoBancarioService::class)->executar($conteudo, 'ERR.CRT');

        $this->assertSame(StatusRetornoBancario::Falha, $retorno->status);
        $this->assertSame(1, $retorno->quantidade_erros);
        $this->assertStringContainsString('não encontrada', (string) $retorno->itens->first()->mensagem);
    }

    public function test_codigo_09_exclui_titulo_sem_liquidar(): void
    {
        RemessaItem::query()
            ->where('cobranca_id', $this->cobranca->id)
            ->update(['enviado_remessa' => 2]);

        $conteudo = SicrediRetornoCnab240Fixture::arquivoComOcorrencias([
            [
                'codigo' => '09',
                'nosso_numero' => $this->cobranca->nosso_numero,
                'numero_registro' => $this->cobranca->numero_registro,
                'com_u' => true,
                'valor_pago' => 0,
            ],
        ]);

        $retorno = app(ProcessarRetornoBancarioService::class)->executar($conteudo, 'EXC.CRT');

        $this->assertSame(StatusRetornoBancario::Concluido, $retorno->status);
        $this->assertSame(1, $retorno->quantidade_excluidas);
        $this->assertSame(0, $retorno->quantidade_liquidadas);
        $this->assertSame(AcaoRetornoItem::ExcluirTitulo, $retorno->itens->first()->acao);

        $this->cobranca->refresh();
        $this->assertSame(StatusCobranca::Cancelada, $this->cobranca->status);
        $this->assertNull($this->cobranca->pago_em);
    }
}
