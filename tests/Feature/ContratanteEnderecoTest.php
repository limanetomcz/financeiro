<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contratante;
use App\Services\Contrato\CriarContratoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratanteEnderecoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-23'));

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

    public function test_grava_endereco_do_pagador_na_criacao(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-END',
                'tipo' => 'pf',
                'nome' => 'Pagador Com Endereco',
                'documento' => '12345678901',
                'endereco' => 'RUA DAS FLORES, 100',
                'bairro' => 'CENTRO',
                'cidade' => 'CAICO',
                'cep' => '59300-000',
                'uf' => 'rn',
            ],
            'vigencia_inicio' => '2026-07-01',
            'vigencia_fim' => '2027-06-30',
            'chave_plano_sigoweb' => 'PLANO-END',
            'valor_total' => 120,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-07-10',
            'perfil_pagamento' => 'boleto_parcelado',
            'modo_emissao' => 'escalonada',
        ]);

        $contratante = $contrato->contratante;
        $this->assertSame('RUA DAS FLORES, 100', $contratante->endereco);
        $this->assertSame('CENTRO', $contratante->bairro);
        $this->assertSame('CAICO', $contratante->cidade);
        $this->assertSame('59300000', $contratante->cep);
        $this->assertSame('RN', $contratante->uf);
    }

    public function test_atualiza_endereco_em_contratante_existente(): void
    {
        Contratante::query()->create([
            'chave_sigoweb' => 'BEN-END-UPD',
            'tipo' => 'pf',
            'nome' => 'Sem Endereco',
            'documento' => '12345678901',
        ]);

        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-END-UPD',
                'tipo' => 'pf',
                'nome' => 'Com Endereco Novo',
                'documento' => '12345678901',
                'endereco' => 'AV PRINCIPAL, 50',
                'bairro' => 'PARAIBA',
                'cidade' => 'CAICO',
                'cep' => '59300000',
                'uf' => 'RN',
            ],
            'vigencia_inicio' => '2026-07-01',
            'vigencia_fim' => '2027-06-30',
            'chave_plano_sigoweb' => 'PLANO-END-UPD',
            'valor_total' => 100,
            'quantidade_parcelas' => 10,
            'primeiro_vencimento' => '2026-07-10',
            'perfil_pagamento' => 'boleto_parcelado',
            'modo_emissao' => 'escalonada',
        ]);

        $contratante = $contrato->contratante->fresh();
        $this->assertSame('Com Endereco Novo', $contratante->nome);
        $this->assertSame('AV PRINCIPAL, 50', $contratante->endereco);
        $this->assertSame('PARAIBA', $contratante->bairro);
        $this->assertSame('CAICO', $contratante->cidade);
        $this->assertSame('59300000', $contratante->cep);
        $this->assertSame('RN', $contratante->uf);
    }

    public function test_nao_emite_cobranca_sem_endereco(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-END-SEM',
                'tipo' => 'pf',
                'nome' => 'Sem Endereco',
                'documento' => '12345678901',
            ],
            'vigencia_inicio' => '2026-07-01',
            'vigencia_fim' => '2027-06-30',
            'chave_plano_sigoweb' => 'PLANO-END-SEM',
            'valor_total' => 120,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-07-10',
            'perfil_pagamento' => 'boleto_parcelado',
            'modo_emissao' => 'imediata',
        ]);

        $parcela = $contrato->parcelas->first();

        $this->expectException(\App\Exceptions\DominioException::class);
        $this->expectExceptionMessage('sem endereço completo');

        app(\App\Services\Cobranca\EmitirCobrancaConsolidadaService::class)->executar(
            [$parcela->id],
            '2026-08-10',
            ['meio' => 'boleto']
        );
    }
}
