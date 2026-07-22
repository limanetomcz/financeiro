<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusContrato;
use App\Exceptions\DominioException;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Services\Contrato\CriarContratoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoPlanoUnicoNoPeriodoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

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

    private function payload(string $chave, string $plano, string $inicio = '2026-01-01', string $fim = '2026-12-31'): array
    {
        return [
            'contratante' => [
                'chave_sigoweb' => $chave,
                'tipo' => 'pf',
                'nome' => 'Pessoa Multiplano',
            ],
            'vigencia_inicio' => $inicio,
            'vigencia_fim' => $fim,
            'chave_plano_sigoweb' => $plano,
            'valor_total' => 100,
            'quantidade_parcelas' => 1,
            'primeiro_vencimento' => $inicio,
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Imediata->value,
        ];
    }

    public function test_bloqueia_mesmo_plano_periodo_sobreposto(): void
    {
        app(CriarContratoService::class)->executar($this->payload('BEN-DUP', 'PLANO-A'));

        $this->expectException(DominioException::class);
        $this->expectExceptionMessage('Já existe financeiro ativo');

        app(CriarContratoService::class)->executar($this->payload('BEN-DUP', 'PLANO-A'));
    }

    public function test_permite_mesmo_cpf_em_planos_diferentes(): void
    {
        $a = app(CriarContratoService::class)->executar($this->payload('BEN-MULTI', 'PLANO-A'));
        $b = app(CriarContratoService::class)->executar($this->payload('BEN-MULTI', 'PLANO-B'));

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame($a->contratante_id, $b->contratante_id);
        $this->assertSame(2, Contrato::query()->where('contratante_id', $a->contratante_id)->count());
    }

    public function test_permite_mesmo_plano_apos_encerrar_contrato_anterior(): void
    {
        $antigo = app(CriarContratoService::class)->executar($this->payload('BEN-RENOV', 'PLANO-A'));
        $antigo->update(['status' => StatusContrato::Encerrado]);

        $novo = app(CriarContratoService::class)->executar($this->payload('BEN-RENOV', 'PLANO-A'));

        $this->assertSame('PLANO-A', $novo->chave_plano_sigoweb);
        $this->assertSame(StatusContrato::Ativo, $novo->status);
    }

    public function test_permite_mesmo_plano_em_periodos_sem_sobreposicao(): void
    {
        app(CriarContratoService::class)->executar(
            $this->payload('BEN-SEQ', 'PLANO-A', '2025-01-01', '2025-12-31')
        );

        $novo = app(CriarContratoService::class)->executar(
            $this->payload('BEN-SEQ', 'PLANO-A', '2026-01-01', '2026-12-31')
        );

        $this->assertSame('2026-01-01', $novo->vigencia_inicio->toDateString());
    }

    public function test_exige_plano(): void
    {
        $this->expectException(DominioException::class);
        $this->expectExceptionMessage('chave_plano_sigoweb');

        $dados = $this->payload('BEN-SEM', 'PLANO-A');
        $dados['chave_plano_sigoweb'] = '';

        app(CriarContratoService::class)->executar($dados);
    }
}
