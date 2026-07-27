<?php

namespace Tests\Unit;

use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Fatura;
use App\Services\Empresa\UpsertEmpresaPjService;
use App\Services\Fatura\SolicitarFaturaPjService;
use App\Services\Fatura\SolicitarFaturasPjLoteService;
use App\Services\Integracao\SigoLaravelClient;
use Tests\TestCase;

class SolicitarFaturasPjLoteServiceTest extends TestCase
{
    public function test_lote_com_planos_override_agrega_solicitadas_puladas_e_erros(): void
    {
        $sigo = $this->createMock(SigoLaravelClient::class);
        $sigo->expects($this->never())->method('listarPlanosEmpresa');

        $ok = new Fatura;
        $ok->forceFill([
            'id' => 'fat-ok',
            'numero' => '202606/0001',
            'status' => StatusFatura::Aberta,
            'mensagem_erro' => null,
        ]);

        $solicitar = new class($this->createMock(UpsertEmpresaPjService::class), $ok) extends SolicitarFaturaPjService
        {
            public function __construct(UpsertEmpresaPjService $upsert, private Fatura $ok)
            {
                parent::__construct($upsert);
            }

            public function executar(
                string $chavePlano,
                string $competencia,
                ?string $vencimento = null,
                bool $sincrono = false,
                ?string $bearerToken = null,
                ?array $dadosOverride = null,
                float $percentualReajuste = 0.0,
                ?string $dataBase = null,
            ): Fatura {
                return match ($chavePlano) {
                    'P1' => $this->ok,
                    'P2' => throw new DominioException('Já existe fatura para a competência 2026-06.'),
                    'P3' => throw new DominioException('Empresa sem vidas elegíveis.'),
                    default => throw new DominioException('plano inesperado'),
                };
            }
        };

        $lote = new SolicitarFaturasPjLoteService($sigo, $solicitar);
        $resultado = $lote->executar(
            '2026-06',
            '2026-06-15',
            null,
            true,
            null,
            0.0,
            ['P1', 'P2', 'P3'],
        );

        $this->assertSame(3, $resultado['total_planos']);
        $this->assertSame('2026-06-15', $resultado['data_base']);
        $this->assertCount(1, $resultado['solicitadas']);
        $this->assertSame('P1', $resultado['solicitadas'][0]['plano']);
        $this->assertSame('aberta', $resultado['solicitadas'][0]['status']);
        $this->assertCount(1, $resultado['puladas']);
        $this->assertSame('P2', $resultado['puladas'][0]['plano']);
        $this->assertCount(1, $resultado['erros']);
        $this->assertSame('P3', $resultado['erros'][0]['plano']);
    }

    public function test_lote_sem_planos_lanca_dominio(): void
    {
        $solicitar = $this->createMock(SolicitarFaturaPjService::class);
        $sigo = $this->createMock(SigoLaravelClient::class);
        $sigo->method('listarPlanosEmpresa')->willReturn([]);

        $lote = new SolicitarFaturasPjLoteService($sigo, $solicitar);

        $this->expectException(DominioException::class);
        $this->expectExceptionMessage('Nenhum plano empresarial');
        $lote->executar('2026-06', '2026-06-15');
    }
}
