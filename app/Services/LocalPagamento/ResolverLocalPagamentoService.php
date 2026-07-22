<?php

namespace App\Services\LocalPagamento;

use App\Exceptions\DominioException;
use App\Models\LocalPagamento;
use App\Models\TaxaLocalPagamento;

/**
 * Resolve canal + taxa a partir do código interno ou do LOC_CODIGO legado.
 */
class ResolverLocalPagamentoService
{
    /**
     * @return array{local: LocalPagamento, taxa: ?TaxaLocalPagamento}
     */
    public function resolver(array $entrada): array
    {
        $codigoLegado = isset($entrada['codigo_legado']) ? (string) $entrada['codigo_legado'] : null;
        $codigoLocal = isset($entrada['local_pagamento_codigo']) ? (string) $entrada['local_pagamento_codigo'] : null;
        $taxaId = isset($entrada['taxa_id']) ? (string) $entrada['taxa_id'] : null;
        $naData = isset($entrada['na_data']) ? (string) $entrada['na_data'] : now()->toDateString();

        if ($codigoLegado !== null && $codigoLegado !== '') {
            return $this->porCodigoLegado($codigoLegado, $naData);
        }

        if ($taxaId) {
            $taxa = TaxaLocalPagamento::query()->with('localPagamento')->find($taxaId);
            if (! $taxa || ! $taxa->ativo || ! $taxa->vigenteEm($naData)) {
                throw new DominioException('Taxa de local de pagamento inválida ou fora de vigência.');
            }

            return ['local' => $taxa->localPagamento, 'taxa' => $taxa];
        }

        if (! $codigoLocal) {
            throw new DominioException('Informe local_pagamento_codigo, taxa_id ou codigo_legado.');
        }

        $local = LocalPagamento::query()
            ->where('codigo', $codigoLocal)
            ->where('ativo', true)
            ->first();

        if (! $local) {
            throw new DominioException("Local de pagamento '{$codigoLocal}' não encontrado.");
        }

        $taxa = null;
        if ($local->exigeTaxa()) {
            if (! $taxaId) {
                throw new DominioException('Local de cartão exige taxa_id (ou codigo_legado da condição).');
            }
        }

        return ['local' => $local, 'taxa' => $taxa];
    }

    /**
     * @return array{local: LocalPagamento, taxa: ?TaxaLocalPagamento}
     */
    public function porCodigoLegado(string $codigoLegado, ?string $naData = null): array
    {
        $naData = $naData ?: now()->toDateString();

        $taxa = TaxaLocalPagamento::query()
            ->with('localPagamento')
            ->where('codigo_legado', $codigoLegado)
            ->where('ativo', true)
            ->first();

        if ($taxa) {
            if (! $taxa->vigenteEm($naData)) {
                throw new DominioException("Taxa legado {$codigoLegado} fora de vigência em {$naData}.");
            }

            return ['local' => $taxa->localPagamento, 'taxa' => $taxa];
        }

        $local = LocalPagamento::query()
            ->where(function ($q) use ($codigoLegado) {
                $q->where('codigo', $codigoLegado)
                    ->orWhere('codigo_legado', $codigoLegado);
            })
            ->where('ativo', true)
            ->first();

        if (! $local) {
            throw new DominioException("Código legado de pagamento '{$codigoLegado}' não encontrado.");
        }

        return ['local' => $local, 'taxa' => null];
    }

    /**
     * Snapshot para gravar na cobrança na baixa.
     *
     * @return array<string, mixed>
     */
    public function snapshotParaCobranca(LocalPagamento $local, ?TaxaLocalPagamento $taxa, float $valorPrincipal): array
    {
        $taxaPercentual = $taxa ? (float) $taxa->taxa_percentual : null;
        $valorTaxa = null;
        if ($taxaPercentual !== null && $taxaPercentual > 0) {
            $valorTaxa = round($valorPrincipal * ($taxaPercentual / 100), 2);
        }

        $descricao = $local->descricao;
        if ($taxa) {
            $descricao = $taxa->descricao;
        }

        return [
            'local_pagamento_id' => $local->id,
            'taxa_local_pagamento_id' => $taxa?->id,
            'local_pagamento' => $taxa?->codigo_legado ?: $local->codigo,
            'local_pagamento_descricao' => $descricao,
            'taxa_percentual' => $taxaPercentual,
            'valor_taxa' => $valorTaxa,
            'modalidade' => $taxa?->modalidade?->value,
            'bandeira' => $taxa?->bandeira?->value,
            'meio' => $local->tipo->value,
        ];
    }
}
