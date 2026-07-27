<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Services\Fatura\SolicitarFaturasPjLoteService;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra “gerar todas”: lista planos E e solicita cada fatura (jobs ProcessarFaturaPj).
 * Sem entidade Lote — só enfileira o trabalho para o HTTP do Sigoweb liberar na hora.
 */
class SolicitarFaturasPjLoteJob extends TenantJob
{
    public int $timeout = 900;

    /**
     * @param  list<string>|null  $planosOverride
     */
    public function __construct(
        public string $competencia,
        public ?string $dataBase = null,
        public ?string $vencimento = null,
        public ?string $bearerToken = null,
        public float $percentualReajuste = 0.0,
        public ?array $planosOverride = null,
        ?string $clienteId = null,
    ) {
        parent::__construct($clienteId);
    }

    protected function queueGroup(): string
    {
        return 'cobranca';
    }

    protected function handleForCliente(Cliente $cliente): void
    {
        $resultado = app(SolicitarFaturasPjLoteService::class)->executar(
            $this->competencia,
            $this->dataBase,
            $this->vencimento,
            false, // cada fatura: processando + ProcessarFaturaPjJob
            $this->bearerToken,
            $this->percentualReajuste,
            $this->planosOverride,
        );

        Log::info('faturas.pj.lote.enfileirado', [
            'cliente_id' => $cliente->id,
            'competencia' => $resultado['competencia'],
            'data_base' => $resultado['data_base'],
            'total_planos' => $resultado['total_planos'],
            'solicitadas' => count($resultado['solicitadas']),
            'puladas' => count($resultado['puladas']),
            'erros' => count($resultado['erros']),
        ]);
    }
}
