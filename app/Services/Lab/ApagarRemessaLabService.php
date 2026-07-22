<?php

namespace App\Services\Lab;

use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Remessa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga remessa de lab e as cobranças (boletos) vinculadas,
 * devolvendo as parcelas para aberta — permite retestar o fluxo.
 */
class ApagarRemessaLabService
{
    public function executar(string $remessaId): array
    {
        if (! config('financeiro.lab_limpeza_habilitada')) {
            throw new DominioException('Limpeza de lab desabilitada neste ambiente.');
        }

        $remessa = Remessa::query()->with('itens')->find($remessaId);

        if (! $remessa) {
            throw new DominioException('Remessa não encontrada.');
        }

        return DB::transaction(function () use ($remessa) {
            $cobrancaIds = $remessa->itens
                ->pluck('cobranca_id')
                ->filter()
                ->unique()
                ->values();

            $parcelasResetadas = 0;
            $cobrancasApagadas = 0;

            if ($cobrancaIds->isNotEmpty()) {
                $cobrancas = Cobranca::query()
                    ->with('parcelas')
                    ->whereIn('id', $cobrancaIds)
                    ->get();

                foreach ($cobrancas as $cobranca) {
                    foreach ($cobranca->parcelas as $parcela) {
                        if ($parcela->status === StatusParcela::EmCobranca) {
                            $parcela->update(['status' => StatusParcela::Aberta]);
                            $parcelasResetadas++;
                        }
                    }
                }

                $cobrancasApagadas = Cobranca::query()
                    ->whereIn('id', $cobrancaIds)
                    ->delete();
            }

            $filePath = $remessa->file_path;
            $lote = $remessa->lote;
            $remessa->delete();

            if ($filePath && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            return [
                'message' => 'Remessa e boletos apagados.',
                'lote' => $lote,
                'apagados' => [
                    'remessa' => 1,
                    'cobrancas' => $cobrancasApagadas,
                    'parcelas_resetadas' => $parcelasResetadas,
                ],
            ];
        });
    }
}
