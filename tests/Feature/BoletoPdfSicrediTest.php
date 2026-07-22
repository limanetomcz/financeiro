<?php

namespace Tests\Feature;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\Sicredi\SicrediCodigoBarrasBoleto;
use App\Enums\ModoEmissao;
use App\Enums\PerfilPagamento;
use App\Enums\StatusParcela;
use App\Models\Cliente;
use App\Models\Parcela;
use App\Services\Boleto\GerarPdfBoletoService;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Contrato\CriarContratoService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoletoPdfSicrediTest extends TestCase
{
    use RefreshDatabase;

    public function test_monta_linha_digitavel_com_44_digitos_no_codigo_barras(): void
    {
        $conta = ContaCobranca::fromClienteConfig(ClienteConfig::padraoSerido());
        $barras = (new SicrediCodigoBarrasBoleto(
            conta: $conta,
            nossoNumero: '250380377',
            vencimento: Carbon::parse('2026-07-30'),
            valor: 104.90,
        ))->montar();

        $this->assertSame(44, strlen($barras['codigo_barras']));
        $this->assertStringStartsWith('748', $barras['codigo_barras']);
        $this->assertMatchesRegularExpression('/^7489\d\.?\d+/', str_replace(' ', '', $barras['linha_digitavel_formatada']));
        $this->assertStringContainsString('2207.04.08012', $barras['agencia_codigo_beneficiario']);
        $this->assertSame('0380377', $barras['nosso_numero_exibicao']);
    }

    public function test_gera_pdf_boleto_da_cobranca(): void
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

        $contrato = app(CriarContratoService::class)->executar([
            'contratante' => [
                'chave_sigoweb' => 'BEN-BOL',
                'tipo' => 'pf',
                'nome' => 'Pagador Boleto',
                'documento' => '32869061404',
                'endereco' => 'RUA MANOEL VICENTE, 1096',
                'bairro' => 'PARAIBA',
                'cidade' => 'CAICO',
                'uf' => 'RN',
                'cep' => '59300000',
            ],
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => '2026-12-31',
            'chave_plano_sigoweb' => 'PLANO-BOL',
            'valor_total' => 120,
            'quantidade_parcelas' => 12,
            'primeiro_vencimento' => '2026-01-10',
            'perfil_pagamento' => PerfilPagamento::BoletoParcelado->value,
            'modo_emissao' => ModoEmissao::Escalonada->value,
        ]);

        $parcela = Parcela::query()
            ->where('contrato_id', $contrato->id)
            ->where('status', StatusParcela::Aberta)
            ->firstOrFail();

        $cobranca = app(EmitirCobrancaConsolidadaService::class)->executar(
            [$parcela->id],
            '2026-07-30',
            ['meio' => 'boleto']
        );

        $pdf = app(GerarPdfBoletoService::class)->executar($cobranca);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);

        ClienteContext::clear();
        Carbon::setTestNow();
    }
}
