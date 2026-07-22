<?php

namespace App\Services\Parcela;

use App\Models\Contratante;
use App\Models\LocalPagamento;
use App\Models\Parcela;
use Carbon\Carbon;

class ListarParcelasContratanteService
{
    /**
     * Grid de “mensalidades” do contratante (todas as parcelas dos contratos).
     *
     * @return array{encontrado: bool, chave_sigoweb: string, message?: string, parcelas?: list<array<string, mixed>>}
     */
    public function porChaveSigoweb(string $chaveSigoweb): array
    {
        $contratante = Contratante::query()
            ->where('chave_sigoweb', $chaveSigoweb)
            ->first();

        if (! $contratante) {
            return [
                'encontrado' => false,
                'chave_sigoweb' => $chaveSigoweb,
                'message' => 'Contratante não encontrado no Financeiro.',
                'parcelas' => [],
            ];
        }

        $hoje = Carbon::today()->toDateString();

        $locaisPorCodigo = LocalPagamento::query()
            ->get(['codigo', 'descricao'])
            ->keyBy('codigo');

        $parcelas = Parcela::query()
            ->with([
                'contrato',
                'beneficiarios',
                'cobrancas' => fn ($q) => $q->orderByDesc('created_at'),
            ])
            ->whereHas('contrato', fn ($q) => $q->where('contratante_id', $contratante->id))
            ->orderBy('vencimento')
            ->orderBy('numero')
            ->get();

        $itens = $parcelas->map(function (Parcela $p) use ($hoje, $locaisPorCodigo) {
            $cobranca = $p->cobrancas->first();
            $vencimento = $p->vencimento->toDateString();
            $codigoLocal = $cobranca?->local_pagamento;
            $descLocal = $cobranca?->local_pagamento_descricao
                ?: ($codigoLocal ? ($locaisPorCodigo->get($codigoLocal)?->descricao) : null);

            return [
                'id' => $p->id,
                'contrato_id' => $p->contrato_id,
                'numero' => $p->numero,
                'referencia' => $p->vencimento->format('Ym'),
                'vencimento' => $vencimento,
                'pago_em' => $p->pago_em?->toDateString(),
                'baixado_por' => $p->baixado_por,
                'baixado_por_nome' => $p->baixado_por_nome,
                'baixa_retirada_por' => $p->baixa_retirada_por,
                'baixa_retirada_por_nome' => $p->baixa_retirada_por_nome,
                'baixa_retirada_em' => $p->baixa_retirada_em?->toDateTimeString(),
                'emitida_em' => $p->emitida_em?->toDateString(),
                'valor' => (float) $p->valor,
                'status' => $p->status->value,
                'vencida' => $p->status->value !== 'paga'
                    && $p->status->value !== 'cancelada'
                    && $vencimento < $hoje,
                'perfil_pagamento' => $p->contrato?->perfil_pagamento?->value,
                'chave_plano_sigoweb' => $p->contrato?->chave_plano_sigoweb,
                'chave_familia_sigoweb' => $p->contrato?->chave_familia_sigoweb,
                'cobranca_id' => $cobranca?->id,
                'nosso_numero' => $cobranca?->nosso_numero,
                'numero_registro' => $cobranca?->numero_registro,
                'meio' => $cobranca?->meio,
                'local_pagamento' => $codigoLocal,
                'local_pagamento_descricao' => $descLocal,
                'taxa_percentual' => $cobranca?->taxa_percentual !== null
                    ? (float) $cobranca->taxa_percentual
                    : null,
                'valor_taxa' => $cobranca?->valor_taxa !== null
                    ? (float) $cobranca->valor_taxa
                    : null,
                'valor_juros' => $cobranca?->valor_juros !== null
                    ? (float) $cobranca->valor_juros
                    : null,
                'valor_multa' => $cobranca?->valor_multa !== null
                    ? (float) $cobranca->valor_multa
                    : null,
                'valor_cobranca' => $cobranca?->valor !== null
                    ? (float) $cobranca->valor
                    : null,
                'modalidade' => $cobranca?->modalidade,
                'bandeira' => $cobranca?->bandeira,
                'beneficiarios' => $p->beneficiarios->map(fn ($b) => [
                    'chave_sigoweb' => $b->chave_sigoweb,
                    'nome' => $b->nome,
                    'documento' => $b->documento,
                    'tipo_dependencia' => $b->tipo_dependencia,
                    'valor' => (float) $b->valor,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'encontrado' => true,
            'chave_sigoweb' => $chaveSigoweb,
            'contratante' => [
                'id' => $contratante->id,
                'nome' => $contratante->nome,
                'documento' => $contratante->documento,
            ],
            'parcelas' => $itens,
        ];
    }
}
