<?php

namespace App\Services\Fatura;

use App\Enums\NaturezaLancamento;
use App\Enums\StatusFatura;
use App\Exceptions\DominioException;
use App\Models\Fatura;
use App\Models\FaturaLancamento;
use Illuminate\Support\Facades\DB;

class AdicionarLancamentoFaturaService
{
    /**
     * Inclui lançamento manual (ex.: desconto) e recalcula totais.
     * Só fatura aberta/rascunho (antes da cobrança).
     *
     * @param  array{
     *   codigo: string,
     *   descricao?: string|null,
     *   natureza: string,
     *   valor: float|int|string,
     *   ordem?: int|null
     * }  $dados
     */
    public function executar(Fatura $fatura, array $dados): Fatura
    {
        return DB::transaction(function () use ($fatura, $dados) {
            $fatura = Fatura::query()->whereKey($fatura->id)->lockForUpdate()->firstOrFail();

            if (! in_array($fatura->status, [StatusFatura::Aberta, StatusFatura::Rascunho], true)) {
                throw new DominioException(
                    'Só é possível adicionar lançamento em fatura aberta ou rascunho (antes da cobrança).'
                );
            }

            $codigo = trim((string) ($dados['codigo'] ?? ''));
            if ($codigo === '') {
                throw new DominioException('Código do lançamento é obrigatório.');
            }

            $natureza = NaturezaLancamento::tryFrom((string) ($dados['natureza'] ?? ''));
            if ($natureza === null) {
                throw new DominioException(
                    'Natureza inválida. Use: base, retencao, acrescimo ou informativo.'
                );
            }

            $valor = round((float) ($dados['valor'] ?? 0), 2);
            if ($valor < 0) {
                throw new DominioException('Valor do lançamento deve ser >= 0 (sinal vem da natureza).');
            }

            $maxOrdem = (int) $fatura->lancamentos()->max('ordem');
            $ordem = isset($dados['ordem']) ? (int) $dados['ordem'] : $maxOrdem + 1;

            FaturaLancamento::query()->create([
                'fatura_id' => $fatura->id,
                'codigo' => $codigo,
                'descricao' => trim((string) ($dados['descricao'] ?? $codigo)) ?: $codigo,
                'natureza' => $natureza,
                'origem' => 'manual',
                'valor' => $valor,
                'ordem' => $ordem,
                'meta' => ['origem_operador' => true],
            ]);

            $this->recalcularTotais($fatura);

            return $fatura->fresh(['lancamentos', 'contratante', 'parcelas']);
        });
    }

    public function recalcularTotais(Fatura $fatura): void
    {
        $bruto = 0.0;
        $retencoes = 0.0;
        $acrescimos = 0.0;

        foreach ($fatura->lancamentos()->get() as $lanc) {
            $valor = (float) $lanc->valor;
            match ($lanc->natureza) {
                NaturezaLancamento::Base => $bruto += $valor,
                NaturezaLancamento::Retencao => $retencoes += $valor,
                NaturezaLancamento::Acrescimo => $acrescimos += $valor,
                NaturezaLancamento::Informativo => null,
            };
        }

        $fatura->update([
            'valor_bruto' => round($bruto, 2),
            'valor_retencoes' => round($retencoes, 2),
            'valor_acrescimos' => round($acrescimos, 2),
            'valor_liquido' => round($bruto - $retencoes + $acrescimos, 2),
        ]);
    }
}
