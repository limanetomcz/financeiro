<?php

namespace App\Bancario\Selecao\Fontes;

use App\Bancario\DTO\FiltroRemessa;
use App\Bancario\Selecao\MapeadorCobrancaParaTitulo;
use App\Contracts\Bancario\FonteTituloRemessaInterface;
use App\Enums\OperacaoRemessa;
use App\Enums\StatusCobranca;
use App\Enums\StatusRemessa;
use App\Models\Cobranca;
use App\Models\RemessaItem;
use Illuminate\Support\Collection;

/**
 * Braços ocorrência 06 da view (alteração de vencimento).
 *
 * Legado: já registrado (enviadoremessa=2) + baixa tipo 02 + vencimento diferente do enviado.
 * Aqui: item anterior concluído com vencimento != vencimento atual da cobrança.
 */
class AlteracaoVencimentoCobrancasFonte implements FonteTituloRemessaInterface
{
    public function __construct(
        private readonly MapeadorCobrancaParaTitulo $mapeador,
    ) {}

    public function nome(): string
    {
        return 'alteracao_vencimento_06';
    }

    public function buscar(FiltroRemessa $filtro): Collection
    {
        $hoje = now()->toDateString();

        $candidatas = RemessaItem::query()
            ->where('operacao', OperacaoRemessa::Entrada)
            ->where('enviado_remessa', 2)
            ->whereNotNull('cobranca_id')
            ->whereHas('remessa', fn ($q) => $q->where('status', StatusRemessa::Concluida))
            ->with('cobranca.contratante')
            ->get()
            ->filter(function (RemessaItem $item) use ($filtro, $hoje) {
                $cobranca = $item->cobranca;
                if (! $cobranca || $cobranca->status !== StatusCobranca::Aberta) {
                    return false;
                }
                if ($cobranca->vencimento->toDateString() <= $hoje) {
                    return false;
                }
                if ($cobranca->vencimento->equalTo($item->vencimento)) {
                    return false;
                }

                $v = $cobranca->vencimento->toDateString();

                return $v >= $filtro->vencimentoInicial->toDateString()
                    && $v <= $filtro->vencimentoFinal->toDateString();
            });

        return $candidatas
            ->map(fn (RemessaItem $item) => $this->mapeador->mapear(
                $item->cobranca,
                $filtro->conta,
                OperacaoRemessa::Alteracao,
            ))
            ->unique(fn ($t) => $t->cobrancaId)
            ->values();
    }
}
