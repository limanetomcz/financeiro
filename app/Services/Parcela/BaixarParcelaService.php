<?php

namespace App\Services\Parcela;

use App\Enums\StatusCobranca;
use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Parcela;
use App\Services\Auditoria\AuditoriaFinanceira;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Support\Auth\OperadorAtual;
use Illuminate\Support\Facades\DB;

/**
 * Baixa de parcela (lab / caixa): garante cobrança aberta e liquida com local + operador.
 */
class BaixarParcelaService
{
    public function __construct(
        private readonly EmitirCobrancaConsolidadaService $emitirCobranca,
        private readonly LiquidarCobrancaService $liquidarCobranca,
        private readonly AuditoriaFinanceira $auditoria,
    ) {}

    /**
     * @param  array{
     *   pago_em?: ?string,
     *   local_pagamento_codigo?: ?string,
     *   codigo_legado?: ?string,
     *   taxa_id?: ?string,
     *   operador?: array{login?: string, nome?: ?string}
     * }  $opcoes
     */
    public function executar(string $parcelaId, array $opcoes = []): Cobranca
    {
        $operador = OperadorAtual::resolver($opcoes['operador'] ?? null);

        return DB::transaction(function () use ($parcelaId, $opcoes, $operador) {
            $parcela = Parcela::query()->with('contrato')->whereKey($parcelaId)->lockForUpdate()->firstOrFail();

            if ($parcela->status === StatusParcela::Paga) {
                throw new DominioException('Parcela já está paga.');
            }

            if (in_array($parcela->status, [StatusParcela::Cancelada, StatusParcela::Perdida], true)) {
                throw new DominioException('Parcela não pode ser baixada (status: '.$parcela->status->value.').');
            }

            if ($parcela->status === StatusParcela::Prevista) {
                $parcela->update([
                    'status' => StatusParcela::Aberta,
                    'emitida_em' => $parcela->emitida_em ?? now()->toDateString(),
                ]);
                $parcela->refresh();
            }

            $cobranca = $this->cobrancaAbertaDaParcela($parcela);

            if (! $cobranca) {
                if ($parcela->status !== StatusParcela::Aberta) {
                    throw new DominioException('Parcela em cobrança sem cobrança aberta vinculada.');
                }

                $cobranca = $this->emitirCobranca->executar(
                    [$parcela->id],
                    $parcela->vencimento->toDateString(),
                    ['meio' => null]
                );
            }

            $cobranca = $this->liquidarCobranca->executar($cobranca, $opcoes['pago_em'] ?? null, [
                'codigo_legado' => $opcoes['codigo_legado'] ?? null,
                'local_pagamento_codigo' => $opcoes['local_pagamento_codigo'] ?? null,
                'taxa_id' => $opcoes['taxa_id'] ?? null,
                'operador' => $operador,
            ]);

            $this->auditoria->registrar('parcela.baixada', [
                'parcela_id' => $parcelaId,
                'cobranca_id' => $cobranca->id,
                'operador' => $operador,
                'local_pagamento' => $cobranca->local_pagamento,
            ]);

            return $cobranca;
        });
    }

    private function cobrancaAbertaDaParcela(Parcela $parcela): ?Cobranca
    {
        return $parcela->cobrancas()
            ->where('status', StatusCobranca::Aberta->value)
            ->orderByDesc('created_at')
            ->first();
    }
}
