<?php

namespace App\Services\Lab;

use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Contratante;
use App\Models\Contrato;
use App\Models\ContratoBeneficiario;
use App\Models\Fatura;
use App\Models\Parcela;
use App\Models\ParcelaBeneficiario;
use App\Models\Remessa;
use App\Models\RemessaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Limpeza lógica do contratante para testes (lab).
 * Soft delete em cascata; libera chave_sigoweb para recriar.
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

            $remessaIds = collect();
            if ($cobrancaIds->isNotEmpty()) {
                $remessaIds = RemessaItem::query()
                    ->whereIn('cobranca_id', $cobrancaIds)
                    ->pluck('remessa_id')
                    ->unique()
                    ->values();
            }

            $contagens = [
                'remessas' => $remessaIds->count(),
                'remessa_itens' => 0,
                'cobrancas' => $cobrancaIds->count(),
                'parcelas' => $parcelaIds->count(),
                'contratos' => $contratoIds->count(),
                'faturas' => $faturaIds->count(),
                'contratante' => 1,
            ];

            if ($remessaIds->isNotEmpty()) {
                $contagens['remessa_itens'] = RemessaItem::query()
                    ->whereIn('remessa_id', $remessaIds)
                    ->count();

                $arquivos = Remessa::query()
                    ->whereIn('id', $remessaIds)
                    ->pluck('file_path')
                    ->filter();

                RemessaItem::query()->whereIn('remessa_id', $remessaIds)->get()->each->delete();
                Remessa::query()->whereIn('id', $remessaIds)->get()->each->delete();

                foreach ($arquivos as $path) {
                    if ($path && Storage::disk('local')->exists($path)) {
                        Storage::disk('local')->delete($path);
                    }
                }
            } elseif ($cobrancaIds->isNotEmpty()) {
                $contagens['remessa_itens'] = RemessaItem::query()
                    ->whereIn('cobranca_id', $cobrancaIds)
                    ->count();

                RemessaItem::query()
                    ->whereIn('cobranca_id', $cobrancaIds)
                    ->update(['cobranca_id' => null]);
            }

            if ($cobrancaIds->isNotEmpty()) {
                Cobranca::query()->whereIn('id', $cobrancaIds)->get()->each->delete();
            }

            if ($faturaIds->isNotEmpty()) {
                Fatura::query()->whereIn('id', $faturaIds)->get()->each->delete();
            }

            if ($parcelaIds->isNotEmpty()) {
                ParcelaBeneficiario::query()->whereIn('parcela_id', $parcelaIds)->get()->each->delete();
                Parcela::query()->whereIn('id', $parcelaIds)->get()->each->delete();
            }

            if ($contratoIds->isNotEmpty()) {
                ContratoBeneficiario::query()->whereIn('contrato_id', $contratoIds)->get()->each->delete();
                Contrato::query()->whereIn('id', $contratoIds)->get()->each->delete();
            }

            // Libera unique (cliente_id, chave_sigoweb) para recriar no lab.
            $contratante->update([
                'chave_sigoweb' => $chaveSigoweb.'#del#'.substr(str_replace('-', '', $contratante->id), 0, 8),
            ]);
            $contratante->delete();

            return [
                'encontrado' => true,
                'chave_sigoweb' => $chaveSigoweb,
                'message' => 'Financeiro do contratante excluído (lógico), incluindo boletos e remessas.',
                'apagados' => $contagens,
            ];
        });
    }
}
