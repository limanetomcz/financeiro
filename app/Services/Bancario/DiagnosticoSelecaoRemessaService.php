<?php

namespace App\Services\Bancario;

use App\Bancario\DTO\FiltroRemessa;
use App\Enums\StatusCobranca;
use App\Enums\StatusRemessa;
use App\Models\Cobranca;
use App\Models\RemessaItem;

/**
 * Conta por que cobranças abertas não entraram na remessa (lab / suporte).
 */
class DiagnosticoSelecaoRemessaService
{
    /**
     * @return array{
     *     abertas_boleto: int,
     *     elegiveis: int,
     *     vencidas_ou_hoje: int,
     *     fora_intervalo: int,
     *     sem_documento: int,
     *     ja_em_remessa: int
     * }
     */
    public function executar(FiltroRemessa $filtro): array
    {
        $hoje = now()->toDateString();
        $ini = $filtro->vencimentoInicial->toDateString();
        $fim = $filtro->vencimentoFinal->toDateString();

        $jaEnviadas = RemessaItem::query()
            ->whereIn('enviado_remessa', [1, 2, 3])
            ->whereHas('remessa', fn ($q) => $q->whereIn('status', [
                StatusRemessa::Concluida->value,
                StatusRemessa::Processando->value,
            ]))
            ->whereNotNull('cobranca_id')
            ->pluck('cobranca_id')
            ->all();

        $base = Cobranca::query()
            ->with('contratante')
            ->where('status', StatusCobranca::Aberta)
            ->where(function ($q) {
                $q->whereNull('meio')->orWhere('meio', 'boleto');
            });

        $todas = (clone $base)->get();

        $vencidas = 0;
        $fora = 0;
        $semDoc = 0;
        $ja = 0;
        $elegiveis = 0;

        foreach ($todas as $c) {
            $v = $c->vencimento->toDateString();
            $doc = $c->contratante?->documento;

            if (in_array($c->id, $jaEnviadas, true)) {
                $ja++;

                continue;
            }
            if ($v <= $hoje) {
                $vencidas++;

                continue;
            }
            if ($v < $ini || $v > $fim) {
                $fora++;

                continue;
            }
            if ($doc === null || $doc === '') {
                $semDoc++;

                continue;
            }
            $elegiveis++;
        }

        return [
            'abertas_boleto' => $todas->count(),
            'elegiveis' => $elegiveis,
            'vencidas_ou_hoje' => $vencidas,
            'fora_intervalo' => $fora,
            'sem_documento' => $semDoc,
            'ja_em_remessa' => $ja,
        ];
    }
}
