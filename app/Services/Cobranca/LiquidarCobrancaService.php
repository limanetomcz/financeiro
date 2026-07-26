<?php

namespace App\Services\Cobranca;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Services\LocalPagamento\ResolverLocalPagamentoService;
use App\Services\Parcela\CalcularJurosMultaService;
use App\Support\Auth\OperadorAtual;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiquidarCobrancaService
{
    public function __construct(
        private readonly ResolverLocalPagamentoService $resolverLocal,
        private readonly CalcularJurosMultaService $calcularJuros,
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
     * }|null  $opcoes
     */
    public function executar(Cobranca $cobranca, ?string $pagoEm = null, ?array $opcoes = null): Cobranca
    {
        $opcoes = $opcoes ?? [];
        $operador = OperadorAtual::resolver($opcoes['operador'] ?? null);

        return DB::transaction(function () use ($cobranca, $pagoEm, $opcoes, $operador) {
            $cobranca = Cobranca::query()->whereKey($cobranca->id)->lockForUpdate()->firstOrFail();

            if ($cobranca->status !== StatusCobranca::Aberta) {
                throw new DominioException('Somente cobranças abertas podem ser liquidadas.');
            }

            $quando = $pagoEm
                ? Carbon::parse($pagoEm)
                : (isset($opcoes['pago_em']) && $opcoes['pago_em']
                    ? Carbon::parse($opcoes['pago_em'])
                    : now());

            $this->aplicarEncargosSeSolicitado($cobranca, $quando->toDateString(), $opcoes);
            $cobranca->refresh();

            $dados = [
                'status' => StatusCobranca::Paga,
                'pago_em' => $quando,
                'baixado_por' => $operador['login'],
                'baixado_por_nome' => $operador['nome'],
                'baixa_retirada_por' => null,
                'baixa_retirada_por_nome' => null,
                'baixa_retirada_em' => null,
            ];

            $temLocal = ! empty($opcoes['codigo_legado'])
                || ! empty($opcoes['local_pagamento_codigo'])
                || ! empty($opcoes['taxa_id']);

            if ($temLocal) {
                $resolvido = $this->resolverLocal->resolver([
                    'codigo_legado' => $opcoes['codigo_legado'] ?? null,
                    'local_pagamento_codigo' => $opcoes['local_pagamento_codigo'] ?? null,
                    'taxa_id' => $opcoes['taxa_id'] ?? null,
                    'na_data' => $quando->toDateString(),
                ]);

                $baseTaxa = (float) $cobranca->valor_principal;
                $dados = array_merge(
                    $dados,
                    $this->resolverLocal->snapshotParaCobranca(
                        $resolvido['local'],
                        $resolvido['taxa'],
                        $baseTaxa
                    )
                );
            }

            $cobranca->update($dados);

            $dadosParcela = [
                'status' => StatusParcela::Paga,
                'pago_em' => $quando,
                'baixado_por' => $operador['login'],
                'baixado_por_nome' => $operador['nome'],
                'baixa_retirada_por' => null,
                'baixa_retirada_por_nome' => null,
                'baixa_retirada_em' => null,
            ];

            foreach ($cobranca->parcelas()->lockForUpdate()->get() as $parcela) {
                $parcela->update($dadosParcela);
            }

            $faturas = Fatura::query()
                ->where('cobranca_id', $cobranca->id)
                ->lockForUpdate()
                ->get();

            foreach ($faturas as $fatura) {
                $fatura->update(['status' => StatusFatura::Paga]);

                foreach ($fatura->parcelas()->lockForUpdate()->get() as $parcela) {
                    if ($parcela->status !== StatusParcela::Paga) {
                        $parcela->update($dadosParcela);
                    }
                }
            }

            return $cobranca->fresh(['parcelas', 'localPagamento', 'taxaLocalPagamento']);
        });
    }

    /**
     * @param  array<string, mixed>  $opcoes
     */
    private function aplicarEncargosSeSolicitado(Cobranca $cobranca, string $pagoEm, array $opcoes): void
    {
        // Sem chave: não recalcula (baixa de parcela já aplicou antes; retorno bancário manda valores prontos).
        if (! array_key_exists('aplicar_encargos', $opcoes)
            && ! array_key_exists('valor_juros', $opcoes)
            && ! array_key_exists('valor_multa', $opcoes)) {
            return;
        }

        $aplicar = array_key_exists('aplicar_encargos', $opcoes)
            ? (bool) $opcoes['aplicar_encargos']
            : true;

        $principal = round((float) $cobranca->valor_principal, 2);
        $vencimento = $cobranca->vencimento?->toDateString() ?? $pagoEm;

        $calc = $this->calcularJuros->calcular(
            $principal,
            $vencimento,
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

        $cobranca->update([
            'valor_juros' => $juros,
            'valor_multa' => $multa,
            'valor' => round($principal + $juros + $multa, 2),
        ]);
    }
}
