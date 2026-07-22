<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusParcela;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Parcela;
use App\Services\Contrato\CriarContratoService;
use App\Services\Lab\RegistrarBoletosLabService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabRegistrarBoletosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-22'));
        config(['financeiro.lab_limpeza_habilitada' => true]);

        $cliente = Cliente::query()->create([
            'nome' => 'Uniodonto Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
        ]);
        ClienteContext::set($cliente);

        app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-REG-LAB',
                'tipo' => 'pf',
                'nome' => 'Lab Registrar',
                'documento' => '12345678901',
            ],
            'vigencia_inicio' => '2026-07-01',
            'vigencia_fim' => '2027-06-30',
            'chave_plano_sigoweb' => 'PLANO-REG',
            'valor_total' => 120,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-07-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);
    }

    protected function tearDown(): void
    {
        ClienteContext::clear();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_abre_previstas_e_registra_todas(): void
    {
        $this->assertSame(1, Parcela::query()->where('status', StatusParcela::Aberta)->count());
        $this->assertSame(11, Parcela::query()->where('status', StatusParcela::Prevista)->count());

        $resultado = app(RegistrarBoletosLabService::class)->executar(
            'BEN-REG-LAB',
            '2026-07-22',
            '2027-08-31',
        );

        $this->assertTrue($resultado['encontrado']);
        $this->assertSame(11, $resultado['abertas']);
        $this->assertSame(12, $resultado['registrados']);
        $this->assertSame(12, Cobranca::query()->where('meio', 'boleto')->count());
        $this->assertSame(0, Parcela::query()->where('status', StatusParcela::Prevista)->count());
        $this->assertSame(12, Parcela::query()->where('status', StatusParcela::EmCobranca)->count());
    }
}
