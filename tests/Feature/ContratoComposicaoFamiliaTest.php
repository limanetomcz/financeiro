<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Exceptions\DominioException;
use App\Models\Cliente;
use App\Models\ContratoBeneficiario;
use App\Models\ParcelaBeneficiario;
use App\Services\Contrato\CriarContratoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratoComposicaoFamiliaTest extends TestCase
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

    public function test_soma_familia_define_valor_contrato_e_snapshot_nas_parcelas(): void
    {
        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => '112000099001',
                'tipo' => 'pf',
                'nome' => 'Titular Família',
                'documento' => '11111111111',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-FAM',
            'chave_familia_sigoweb' => '000099',
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
            'beneficiarios' => [
                [
                    'chave_sigoweb' => '112000099001',
                    'nome' => 'Titular Família',
                    'documento' => '11111111111',
                    'tipo_dependencia' => 'titular',
                    'tipodep_sigoweb' => '3',
                    'valor_mensal' => 80,
                ],
                [
                    'chave_sigoweb' => '112000099002',
                    'nome' => 'Dependente Um',
                    'documento' => '22222222222',
                    'tipo_dependencia' => 'dependente',
                    'tipodep_sigoweb' => '1',
                    'valor_mensal' => 40,
                ],
                [
                    'chave_sigoweb' => '112000099003',
                    'nome' => 'Dependente Dois',
                    'documento' => '33333333333',
                    'tipo_dependencia' => 'dependente',
                    'valor_mensal' => 30,
                ],
            ],
        ]);

        // 80+40+30 = 150/mês × 12 = 1800
        $this->assertEquals(150.0, (float) $contrato->valor_mensal_familia);
        $this->assertEquals(1800.0, (float) $contrato->valor_total);
        $this->assertSame('000099', $contrato->chave_familia_sigoweb);
        $this->assertCount(3, $contrato->beneficiarios);
        $this->assertCount(12, $contrato->parcelas);

        $this->assertEquals(3, ContratoBeneficiario::query()->where('contrato_id', $contrato->id)->count());
        $this->assertEquals(36, ParcelaBeneficiario::query()->count()); // 12 × 3

        $parcela1 = $contrato->parcelas->first();
        $this->assertEquals(150.0, (float) $parcela1->valor);
        $this->assertEquals(150.0, (float) $parcela1->beneficiarios->sum('valor'));
        $this->assertEquals(80.0, (float) $parcela1->beneficiarios->firstWhere('chave_sigoweb', '112000099001')->valor);
    }

    public function test_rejeita_valor_total_divergente_da_familia(): void
    {
        $this->expectException(DominioException::class);

        app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-DIV',
                'tipo' => 'pf',
                'nome' => 'Titular',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-DIV',
            'valor_total' => 999,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'beneficiarios' => [
                [
                    'chave_sigoweb' => 'BEN-DIV',
                    'nome' => 'Titular',
                    'tipo_dependencia' => 'titular',
                    'valor_mensal' => 100,
                ],
            ],
        ]);
    }
}
