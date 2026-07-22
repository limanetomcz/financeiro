<?php

namespace App\Services\Financeiro;

use App\Enums\StatusContrato;
use App\Enums\StatusFatura;
use App\Enums\StatusParcela;
use App\Enums\TipoContratante;
use App\Models\Contratante;
use App\Models\Fatura;
use App\Models\Parcela;
use App\Services\Elegibilidade\ElegibilidadeService;
use Carbon\Carbon;

class SituacaoFinanceiraService
{
    public function __construct(private ElegibilidadeService $elegibilidade)
    {
    }

    /**
     * Resumo financeiro do contratante (PF ou PJ) para o Sigoweb.
     *
     * @return array<string, mixed>
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
            ];
        }

        $eleg = $this->elegibilidade->avaliarPorChaveSigoweb($chaveSigoweb);
        $hoje = Carbon::today()->toDateString();

        $contratos = $contratante->contratos()
            ->with(['parcelas' => fn ($q) => $q->orderBy('numero')])
            ->orderByDesc('vigencia_inicio')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'tipo' => $c->tipo,
                'perfil_pagamento' => $c->perfil_pagamento?->value,
                'modo_emissao' => $c->modo_emissao?->value,
                'status' => $c->status?->value,
                'vigencia_inicio' => $c->vigencia_inicio?->toDateString(),
                'vigencia_fim' => $c->vigencia_fim?->toDateString(),
                'valor_total' => (float) $c->valor_total,
                'quantidade_parcelas' => $c->quantidade_parcelas,
            ]);

        $parcelasQuery = Parcela::query()
            ->whereHas('contrato', fn ($q) => $q->where('contratante_id', $contratante->id))
            ->orderBy('vencimento');

        $parcelasAbertas = (clone $parcelasQuery)
            ->whereIn('status', [StatusParcela::Aberta, StatusParcela::EmCobranca])
            ->get();

        $parcelasVencidas = $parcelasAbertas
            ->filter(fn (Parcela $p) => $p->vencimento->toDateString() < $hoje);

        $resumoParcelas = [
            'abertas_qtd' => $parcelasAbertas->count(),
            'abertas_valor' => round((float) $parcelasAbertas->sum('valor'), 2),
            'vencidas_qtd' => $parcelasVencidas->count(),
            'vencidas_valor' => round((float) $parcelasVencidas->sum('valor'), 2),
            'itens' => $parcelasAbertas->map(fn (Parcela $p) => [
                'id' => $p->id,
                'contrato_id' => $p->contrato_id,
                'numero' => $p->numero,
                'vencimento' => $p->vencimento->toDateString(),
                'emitida_em' => $p->emitida_em?->toDateString(),
                'valor' => (float) $p->valor,
                'status' => $p->status->value,
                'vencida' => $p->vencimento->toDateString() < $hoje,
            ])->values(),
        ];

        $faturas = collect();
        $empresaId = $contratante->tipo === TipoContratante::Pj
            ? $contratante->id
            : $contratante->empresa_id;

        if ($empresaId) {
            $faturas = Fatura::query()
                ->where('contratante_id', $empresaId)
                ->whereIn('status', [StatusFatura::Aberta, StatusFatura::EmCobranca, StatusFatura::Rascunho])
                ->orderBy('vencimento')
                ->get();
        }

        $faturasVencidas = $faturas->filter(fn (Fatura $f) => $f->vencimento->toDateString() < $hoje);

        $resumoFaturas = [
            'abertas_qtd' => $faturas->count(),
            'abertas_valor_liquido' => round((float) $faturas->sum('valor_liquido'), 2),
            'vencidas_qtd' => $faturasVencidas->count(),
            'vencidas_valor_liquido' => round((float) $faturasVencidas->sum('valor_liquido'), 2),
            'itens' => $faturas->map(fn (Fatura $f) => [
                'id' => $f->id,
                'competencia' => $f->competencia,
                'vencimento' => $f->vencimento->toDateString(),
                'valor_bruto' => (float) $f->valor_bruto,
                'valor_liquido' => (float) $f->valor_liquido,
                'status' => $f->status->value,
                'vencida' => $f->vencimento->toDateString() < $hoje,
            ])->values(),
        ];

        return [
            'encontrado' => true,
            'contratante' => [
                'id' => $contratante->id,
                'chave_sigoweb' => $contratante->chave_sigoweb,
                'tipo' => $contratante->tipo->value,
                'nome' => $contratante->nome,
                'documento' => $contratante->documento,
                'empresa_id' => $contratante->empresa_id,
            ],
            'elegibilidade' => $eleg,
            'contratos' => $contratos,
            'parcelas' => $resumoParcelas,
            'faturas' => $resumoFaturas,
            'saldo_em_aberto' => round(
                (float) $resumoParcelas['abertas_valor'] + (float) $resumoFaturas['abertas_valor_liquido'],
                2
            ),
        ];
    }
}
