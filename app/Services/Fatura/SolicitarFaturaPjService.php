<?php

namespace App\Services\Fatura;

use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Jobs\ProcessarFaturaPjJob;
use App\Models\Contratante;
use App\Models\Fatura;
use App\Services\Empresa\UpsertEmpresaPjService;
use App\Support\Cliente\ClienteConfig;
use App\Support\Tenant\ClienteContext;
use Carbon\Carbon;

/**
 * Aceita pedido de fatura PJ (async): cria status=processando e despacha job.
 */
class SolicitarFaturaPjService
{
    public function __construct(
        private UpsertEmpresaPjService $upsertEmpresa,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $dadosOverride  Lab/teste: pula Laravel no job
     */
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
        if (! preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            throw new DominioException('Competência deve estar no formato YYYY-MM.');
        }

        $chavePlano = trim($chavePlano);
        if ($chavePlano === '') {
            throw new DominioException('Informe chave_plano_sigoweb.');
        }

        // Data de corte de vidas: incl ≤ DB e (excl nula ou excl > DB).
        // Default alinhado ao Laravel de leitura: fim do mês da competência.
        $dataBaseEfetiva = $dataBase
            ? Carbon::parse($dataBase)->toDateString()
            : Carbon::createFromFormat('Y-m', $competencia)->endOfMonth()->toDateString();

        $cliente = ClienteContext::get();

        // Stub mínimo do pagador (= plano). Job completa endereço/CNPJ via Laravel.
        $empresa = $this->upsertEmpresa->executar([
            'chave_sigoweb' => $chavePlano,
            'nome' => 'Plano '.$chavePlano,
        ]);

        if (Fatura::query()
            ->where('contratante_id', $empresa->id)
            ->where('competencia', $competencia)
            ->whereNotIn('status', [StatusFatura::Erro, StatusFatura::Cancelada])
            ->exists()) {
            throw new DominioException("Já existe fatura para a competência {$competencia}.");
        }

        $maxAbertas = ClienteConfig::pjMaxFaturasAbertasParaGerar($cliente);
        $abertas = Fatura::query()
            ->where('contratante_id', $empresa->id)
            ->whereIn('status', [
                StatusFatura::Processando,
                StatusFatura::Aberta,
                StatusFatura::EmCobranca,
                StatusFatura::Rascunho,
            ])
            ->count();

        if ($abertas >= $maxAbertas) {
            throw new DominioException(
                "Empresa já possui {$abertas} fatura(s) em aberto/processando (limite: {$maxAbertas})."
            );
        }

        $vencimentoData = $vencimento
            ? Carbon::parse($vencimento)
            : Carbon::createFromFormat('Y-m', $competencia)
                ->day(ClienteConfig::pjDiaVencimentoPadrao($cliente));

        $fatura = Fatura::query()->create([
            'contratante_id' => $empresa->id,
            'chave_plano_sigoweb' => $chavePlano,
            'competencia' => $competencia,
            'data_emissao' => now()->toDateString(),
            'vencimento' => $vencimentoData->toDateString(),
            'status' => StatusFatura::Processando,
            'mensagem_erro' => null,
            'meta' => [
                'percentual_reajuste' => $percentualReajuste,
                'data_base' => $dataBaseEfetiva,
                'solicitado_em' => now()->toIso8601String(),
            ],
            'valor_bruto' => 0,
            'valor_retencoes' => 0,
            'valor_acrescimos' => 0,
            'valor_liquido' => 0,
        ]);

        $job = new ProcessarFaturaPjJob(
            $fatura->id,
            $bearerToken,
            $dadosOverride,
            ClienteContext::id(),
        );

        if ($sincrono) {
            $clienteId = ClienteContext::id();
            $job->handle();
            // TenantJob limpa o context no finally — restaura para o request atual.
            $cliente = \App\Models\Cliente::query()->find($clienteId);
            if ($cliente) {
                ClienteContext::set($cliente);
            }

            return $fatura->fresh(['lancamentos', 'contratante']);
        }

        dispatch($job);

        return $fatura->load(['contratante']);
    }
}
