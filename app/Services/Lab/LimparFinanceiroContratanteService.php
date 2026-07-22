<?php

namespace App\Services\Lab;

use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Contratante;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Parcela;
use App\Models\RemessaItem;
use Illuminate\Support\Facades\DB;

/**
 * Limpeza de dados do contratante para testes (lab).
 * Não usar em produção.
 */
class LimparFinanceiroContratanteService
{
    public function porChaveSigoweb(string $chaveSigoweb): array
    {
        if (! config('financeiro.lab_limpeza_habilitada')) {
            throw new DominioException('Limpeza de lab desabilitada neste ambiente.');
        }

        $contratante = Contratante::query()
            ->where('chave_sigoweb', $chaveSigoweb)
            ->first();

        if (! $contratante) {
            return [
                'encontrado' => false,
                'chave_sigoweb' => $chaveSigoweb,
                'message' => 'Nada a limpar — contratante não existe no Financeiro.',
                'apagados' => [],
            ];
        }

        return DB::transaction(function () use ($contratante, $chaveSigoweb) {
            $contratoIds = Contrato::query()
                ->where('contratante_id', $contratante->id)
                ->pluck('id');

            $parcelaIds = Parcela::query()
                ->whereIn('contrato_id', $contratoIds)
                ->pluck('id');

            $cobrancaIds = Cobranca::query()
                ->where('contratante_id', $contratante->id)
                ->pluck('id');

            $faturaIds = Fatura::query()
                ->where('contratante_id', $contratante->id)
                ->pluck('id');

            $contagens = [
                'remessa_itens' => 0,
                'cobrancas' => $cobrancaIds->count(),
                'parcelas' => $parcelaIds->count(),
                'contratos' => $contratoIds->count(),
                'faturas' => $faturaIds->count(),
                'contratante' => 1,
            ];

            if ($cobrancaIds->isNotEmpty()) {
                $contagens['remessa_itens'] = RemessaItem::query()
                    ->whereIn('cobranca_id', $cobrancaIds)
                    ->count();

                RemessaItem::query()
                    ->whereIn('cobranca_id', $cobrancaIds)
                    ->update(['cobranca_id' => null]);
            }

            // Cascata: deletar contratante remove contratos/parcelas/cobranças/faturas
            $contratante->delete();

            return [
                'encontrado' => true,
                'chave_sigoweb' => $chaveSigoweb,
                'message' => 'Financeiro do contratante limpo.',
                'apagados' => $contagens,
            ];
        });
    }
}
