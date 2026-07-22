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
        private readonly CalcularJurosMultaService $calcularJuros,
        private readonly AuditoriaFinanceira $auditoria,
    ) {}

    /**
     * @param  array{
     *   pago_em?: ?string,
     *   local_pagamento_codigo?: ?string,
     *   codigo_legado?: ?string,
     *   taxa_id?: ?string,
     *   aplicar_encargos?: bool,
     *   valor_juros?: float|int|string|null,
     *   valor_multa?: float|int|string|null,
     *   operador?: array{login?: string, nome?: ?string}
     * }  $opcoes
     */
    public function executar(string $parcelaId, array $opcoes = []): Cobranca
    {
        $operador = OperadorAtual::resolver($opcoes['operador'] ?? null);
        $pagoEm = $opcoes['pago_em'] ?? now()->toDateString();

        return DB::transaction(function () use ($parcelaId, $opcoes, $operador, $pagoEm) {
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

            $this->aplicarEncargosNaCobranca($cobranca, $parcela, (string) $pagoEm, $opcoes);
            $cobranca->refresh();

            $cobranca = $this->liquidarCobranca->executar($cobranca, $pagoEm, [
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
                'valor_juros' => $cobranca->valor_juros,
                'valor_multa' => $cobranca->valor_multa,
            ]);

            return $cobranca;
        });
    }

    /**
     * @param  array<string, mixed>  $opcoes
     */
    private function aplicarEncargosNaCobranca(
        Cobranca $cobranca,
        Parcela $parcela,
        string $pagoEm,
        array $opcoes
    ): void {
        $aplicar = array_key_exists('aplicar_encargos', $opcoes)
            ? (bool) $opcoes['aplicar_encargos']
            : true;

        $calc = $this->calcularJuros->calcular(
            (float) $parcela->valor,
            $parcela->vencimento->toDateString(),
            $pagoEm,
            ! $aplicar
        );

        if (! $aplicar || ! $calc['atrasada'] || $calc['carencia_fds_aplicada']) {
            $juros = 0.0;
            $multa = 0.0;
        } else {
            $juros = array_key_exists('valor_juros', $opcoes) && $opcoes['valor_juros'] !== null
                ? round((float) $opcoes['valor_juros'], 2)
                : $calc['valor_juros'];
            $multa = array_key_exists('valor_multa', $opcoes) && $opcoes['valor_multa'] !== null
                ? round((float) $opcoes['valor_multa'], 2)
                : $calc['valor_multa'];
        }

        if ($juros < 0 || $multa < 0) {
            throw new DominioException('Juros e multa não podem ser negativos.');
        }

        $principal = round((float) $cobranca->valor_principal, 2);

        $cobranca->update([
            'valor_juros' => $juros,
            'valor_multa' => $multa,
            'valor' => round($principal + $juros + $multa, 2),
        ]);
    }

    private function cobrancaAbertaDaParcela(Parcela $parcela): ?Cobranca
    {
        return $parcela->cobrancas()
            ->where('status', StatusCobranca::Aberta->value)
            ->orderByDesc('created_at')
            ->first();
    }
}
