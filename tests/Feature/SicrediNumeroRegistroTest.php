<?php

namespace Tests\Feature;

use App\Bancario\DTO\ContaCobranca;
use App\Bancario\Sicredi\SicrediNossoNumeroGenerator;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contratante;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SicrediNumeroRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_registro_yy_contador_dv_e_incrementa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        $cliente = Cliente::query()->create([
            'nome' => 'Seridó',
            'codigo_cooperativa' => '112',
            'chave_sigoweb' => '112',
            'ativo' => true,
            'config' => ClienteConfig::padraoSerido(),
            'contador_boletos_unicred' => 1,
        ]);
        ClienteContext::set($cliente);

        $contratante = Contratante::query()->create([
            'chave_sigoweb' => 'X1',
            'tipo' => 'pf',
            'nome' => 'Teste',
            'documento' => '12345678901',
        ]);

        $cobranca = Cobranca::query()->create([
            'contratante_id' => $contratante->id,
            'tipo' => 'simples',
            'valor_principal' => 10,
            'valor_juros' => 0,
            'valor_multa' => 0,
            'valor' => 10,
            'vencimento' => '2026-04-10',
            'status' => 'aberta',
            'meio' => 'boleto',
        ]);

        $conta = ContaCobranca::fromClienteConfig($cliente->config);
        $gerador = app(SicrediNossoNumeroGenerator::class);
        $resultado = $gerador->garantir($cobranca, $conta);

        $this->assertMatchesRegularExpression('/^26\d{6}\d$/', $resultado['numero_registro']);
        $this->assertSame($resultado['numero_registro'], $resultado['nosso_numero']);
        $this->assertSame(2, (int) $cliente->fresh()->contador_boletos_unicred);

        ClienteContext::clear();
        Carbon::setTestNow();
    }
}
