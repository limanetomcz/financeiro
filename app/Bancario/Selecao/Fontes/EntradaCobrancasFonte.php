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
 * Braços M + MA + F da view (ocorrência 01), no domínio novo = cobranças abertas.
 *
 * Regras portadas da view_remessa_boletos (entrada):
 * - não pago
 * - tem número de registro (garantido no mapear)
 * - ainda não enviado em remessa (enviado 1/2/3)
 * - vencimento > hoje e no intervalo do filtro
 * - documento do pagador presente
 *
 * Fora do piloto: BA (avulso) e DC (cooperado).
 */
class EntradaCobrancasFonte implements FonteTituloRemessaInterface
{
    public function __construct(
        private readonly MapeadorCobrancaParaTitulo $mapeador,
    ) {}

    public function nome(): string
    {
        return 'entrada_cobrancas_M_MA_F';
    }

    public function buscar(FiltroRemessa $filtro): Collection
    {
        $jaEnviadas = RemessaItem::query()
            ->whereIn('enviado_remessa', [1, 2, 3])
            ->whereHas('remessa', fn ($q) => $q->whereIn('status', [
                StatusRemessa::Concluida->value,
                StatusRemessa::Processando->value,
            ]))
            ->whereNotNull('cobranca_id')
            ->pluck('cobranca_id')
            ->all();

        $hoje = now()->toDateString();

        $cobrancas = Cobranca::query()
            ->with('contratante')
            ->where('status', StatusCobranca::Aberta)
            ->where(function ($q) {
                $q->whereNull('meio')->orWhere('meio', 'boleto');
            })
            ->where('vencimento', '>', $hoje)
            ->whereBetween('vencimento', [
                $filtro->vencimentoInicial->toDateString(),
                $filtro->vencimentoFinal->toDateString(),
            ])
            ->whereHas('contratante', fn ($q) => $q->whereNotNull('documento')->where('documento', '!=', ''))
            ->when($jaEnviadas !== [], fn ($q) => $q->whereNotIn('id', $jaEnviadas))
            ->orderBy('vencimento')
            ->get();

        return $cobrancas
            ->map(fn (Cobranca $c) => $this->mapeador->mapear($c, $filtro->conta, OperacaoRemessa::Entrada))
            ->values();
    }
}
