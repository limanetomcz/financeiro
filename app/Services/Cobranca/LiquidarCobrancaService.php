<?php

namespace App\Services\Cobranca;

use App\Enums\StatusCobranca;
use App\Enums\StatusFatura;
use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Fatura;
use App\Services\LocalPagamento\ResolverLocalPagamentoService;
use App\Support\Auth\OperadorAtual;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiquidarCobrancaService
{
    public function __construct(
        private readonly ResolverLocalPagamentoService $resolverLocal
    ) {}

    /**
     * @param  array{
     *   pago_em?: ?string,
     *   local_pagamento_codigo?: ?string,
     *   codigo_legado?: ?string,
     *   taxa_id?: ?string,
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

                $dados = array_merge(
                    $dados,
                    $this->resolverLocal->snapshotParaCobranca(
                        $resolvido['local'],
                        $resolvido['taxa'],
                        (float) $cobranca->valor_principal
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
}
