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
     * @param  array{meio?: ?string, valor_juros?: float|int|string, valor_multa?: float|int|string}  $opcoes
     */
    public function executar(array $parcelaIds, string $vencimento, array $opcoes = []): Cobranca
    {
        if ($parcelaIds === []) {
            throw new DominioException('Informe ao menos uma parcela.');
        }

        $valorJuros = round((float) ($opcoes['valor_juros'] ?? 0), 2);
        $valorMulta = round((float) ($opcoes['valor_multa'] ?? 0), 2);
        $meio = $opcoes['meio'] ?? null;

        if ($valorJuros < 0 || $valorMulta < 0) {
            throw new DominioException('Juros e multa não podem ser negativos.');
        }

        return DB::transaction(function () use ($parcelaIds, $vencimento, $meio, $valorJuros, $valorMulta) {
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

            $valorPrincipal = round((float) $parcelas->sum('valor'), 2);
            $valor = round($valorPrincipal + $valorJuros + $valorMulta, 2);

            $cobranca = Cobranca::query()->create([
                'contratante_id' => $contratanteIds->first(),
                'tipo' => $parcelas->count() > 1 ? TipoCobranca::Consolidada : TipoCobranca::Simples,
                'valor_principal' => $valorPrincipal,
                'valor_juros' => $valorJuros,
                'valor_multa' => $valorMulta,
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
