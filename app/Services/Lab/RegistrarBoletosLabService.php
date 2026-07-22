<?php

namespace App\Services\Lab;

use App\Enums\StatusParcela;
use App\Exceptions\DominioException;
use App\Models\Contratante;
use App\Models\Contrato;
use App\Models\Parcela;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lab: abre previstas e registra boleto em todas as parcelas abertas sem cobrança.
 */
class RegistrarBoletosLabService
{
    public function __construct(
        private readonly AbrirTodasParcelasContratanteLabService $abrirParcelas,
        private readonly EmitirCobrancaConsolidadaService $emitirCobranca,
    ) {}

    /**
     * @return array{
     *     encontrado: bool,
     *     message: string,
     *     abertas: int,
     *     registrados: int,
     *     falhas: list<string>,
     *     cobranca_ids: list<string>
     * }
     */
    public function executar(
        string $chaveSigoweb,
        string $vencimentoInicial,
        string $vencimentoFinal,
    ): array {
        if (! config('financeiro.lab_limpeza_habilitada')) {
            throw new DominioException('Limpeza de lab desabilitada neste ambiente.');
        }

        $abrir = $this->abrirParcelas->porChaveSigoweb($chaveSigoweb);
        if (! ($abrir['encontrado'] ?? false)) {
            return [
                'encontrado' => false,
                'message' => $abrir['message'] ?? 'Contratante não encontrado.',
                'abertas' => 0,
                'registrados' => 0,
                'falhas' => [],
                'cobranca_ids' => [],
            ];
        }

        $contratante = Contratante::query()
            ->where('chave_sigoweb', $chaveSigoweb)
            ->firstOrFail();

        $contratoIds = Contrato::query()
            ->where('contratante_id', $contratante->id)
            ->pluck('id');

        $parcelas = Parcela::query()
            ->whereIn('contrato_id', $contratoIds)
            ->where('status', StatusParcela::Aberta)
            ->whereDoesntHave('cobrancas')
            ->orderBy('vencimento')
            ->orderBy('numero')
            ->get();

        $ini = Carbon::parse($vencimentoInicial)->startOfDay();
        $fim = Carbon::parse($vencimentoFinal)->startOfDay();
        if ($fim->lt($ini)) {
            throw new DominioException('Vencimento final deve ser >= inicial.');
        }

        $hoje = Carbon::today();
        $registrados = 0;
        $falhas = [];
        $cobrancaIds = [];

        foreach ($parcelas as $parcela) {
            try {
                $venc = $this->vencimentoNoIntervalo($parcela->vencimento, $ini, $fim, $hoje);
                $cobranca = DB::transaction(function () use ($parcela, $venc) {
                    return $this->emitirCobranca->executar(
                        [$parcela->id],
                        $venc->toDateString(),
                        ['meio' => 'boleto']
                    );
                });
                $registrados++;
                $cobrancaIds[] = $cobranca->id;
            } catch (\Throwable $e) {
                $falhas[] = 'parcela '.$parcela->numero.': '.$e->getMessage();
            }
        }

        return [
            'encontrado' => true,
            'message' => "Boletos registrados: {$registrados}"
                .(($abrir['abertas'] ?? 0) > 0 ? ' · previstas abertas: '.$abrir['abertas'] : '')
                .($falhas !== [] ? ' · falhas: '.count($falhas) : ''),
            'abertas' => (int) ($abrir['abertas'] ?? 0),
            'registrados' => $registrados,
            'falhas' => $falhas,
            'cobranca_ids' => $cobrancaIds,
        ];
    }

    private function vencimentoNoIntervalo(
        Carbon $parcelaVenc,
        Carbon $ini,
        Carbon $fim,
        Carbon $hoje,
    ): Carbon {
        $venc = $parcelaVenc->copy()->startOfDay();

        if ($venc->gt($hoje) && ! $venc->lt($ini) && ! $venc->gt($fim)) {
            return $venc;
        }

        $candidato = $fim->gt($hoje) ? $fim->copy() : $hoje->copy()->addDay();
        if ($candidato->lt($ini)) {
            $candidato = $ini->gt($hoje) ? $ini->copy() : $hoje->copy()->addDay();
        }
        if ($candidato->lte($hoje)) {
            $candidato = $hoje->copy()->addDay();
        }
        if ($candidato->gt($fim) && $fim->gt($hoje)) {
            $candidato = $fim->copy();
        }

        return $candidato->startOfDay();
    }
}
