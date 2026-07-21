<?php

namespace App\Services\Cobranca;

use App\Enums\StatusCobranca;
use App\Enums\StatusParcela;
use App\Enums\TipoCobranca;
use App\Exceptions\DominioException;
use App\Models\Cobranca;
use App\Models\Parcela;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmitirCobrancaConsolidadaService
{
    /**
     * @param  list<string>  $parcelaIds
     */
    public function executar(array $parcelaIds, string $vencimento, ?string $meio = null): Cobranca
    {
        if ($parcelaIds === []) {
            throw new DominioException('Informe ao menos uma parcela.');
        }

        return DB::transaction(function () use ($parcelaIds, $vencimento, $meio) {
            /** @var Collection<int, Parcela> $parcelas */
            $parcelas = Parcela::query()
                ->with('contrato')
                ->whereIn('id', $parcelaIds)
                ->lockForUpdate()
                ->get();

            if ($parcelas->count() !== count(array_unique($parcelaIds))) {
                throw new DominioException('Uma ou mais parcelas não foram encontradas neste cliente.');
            }

            $contratanteIds = $parcelas->map(fn (Parcela $p) => $p->contrato->contratante_id)->unique();
            if ($contratanteIds->count() !== 1) {
                throw new DominioException('Todas as parcelas devem ser do mesmo contratante.');
            }

            foreach ($parcelas as $parcela) {
                if ($parcela->status !== StatusParcela::Aberta) {
                    throw new DominioException("Parcela {$parcela->numero} não está aberta (status: {$parcela->status->value}).");
                }
            }

            $valor = round((float) $parcelas->sum('valor'), 2);

            $cobranca = Cobranca::query()->create([
                'contratante_id' => $contratanteIds->first(),
                'tipo' => $parcelas->count() > 1 ? TipoCobranca::Consolidada : TipoCobranca::Simples,
                'valor' => $valor,
                'vencimento' => $vencimento,
                'status' => StatusCobranca::Aberta,
                'meio' => $meio,
            ]);

            foreach ($parcelas as $parcela) {
                $cobranca->parcelas()->attach($parcela->id, [
                    'valor_alocado' => $parcela->valor,
                ]);
                $parcela->update(['status' => StatusParcela::EmCobranca]);
            }

            return $cobranca->load('parcelas');
        });
    }
}
