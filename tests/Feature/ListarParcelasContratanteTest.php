<?php

namespace Tests\Feature;

use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Models\Cliente;
use App\Services\Contrato\CriarContratoService;
use App\Services\Parcela\ListarParcelasContratanteService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListarParcelasContratanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_todas_as_parcelas_incluindo_previstas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

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
                'chave_sigoweb' => '112000050001',
                'tipo' => 'pf',
                'nome' => 'Teste Grid',
                'documento' => '07639330408',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-GRID',
            'valor_total' => 1200,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);

        $resultado = app(ListarParcelasContratanteService::class)
            ->porChaveSigoweb('112000050001');

        $this->assertTrue($resultado['encontrado']);
        $this->assertCount(12, $resultado['parcelas']);
        $this->assertArrayHasKey('local_pagamento', $resultado['parcelas'][0]);
        $this->assertArrayHasKey('status', $resultado['parcelas'][0]);
        $this->assertArrayHasKey('beneficiarios', $resultado['parcelas'][0]);
        $this->assertIsArray($resultado['parcelas'][0]['beneficiarios']);

        ClienteContext::clear();
        Carbon::setTestNow();
    }
}
