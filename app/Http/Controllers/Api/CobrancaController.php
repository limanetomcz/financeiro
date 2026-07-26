<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusCobranca;
use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Cobranca\LiquidarCobrancaService;
use App\Services\Parcela\CalcularJurosMultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobrancaController extends Controller
{
    public function consolidar(Request $request, EmitirCobrancaConsolidadaService $service): JsonResponse
    {
        $dados = $request->validate([
            'parcela_ids' => ['required', 'array', 'min:1'],
            'parcela_ids.*' => ['uuid'],
            'vencimento' => ['required', 'date'],
            'meio' => ['nullable', 'string', 'max:20'],
            'valor_juros' => ['nullable', 'numeric', 'min:0'],
            'valor_multa' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $cobranca = $service->executar(
                $dados['parcela_ids'],
                $dados['vencimento'],
                [
                    'meio' => $dados['meio'] ?? null,
                    'valor_juros' => $dados['valor_juros'] ?? 0,
                    'valor_multa' => $dados['valor_multa'] ?? 0,
                ]
            );
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($cobranca, 201);
    }

    public function liquidar(string $id, Request $request, LiquidarCobrancaService $service): JsonResponse
    {
        $dados = $request->validate([
            'pago_em' => ['nullable', 'date'],
            'local_pagamento_codigo' => ['nullable', 'string', 'max:10'],
            'codigo_legado' => ['nullable', 'string', 'max:10'],
            'taxa_id' => ['nullable', 'uuid'],
            'aplicar_encargos' => ['sometimes', 'boolean'],
            'valor_juros' => ['nullable', 'numeric', 'min:0'],
            'valor_multa' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cobranca = Cobranca::query()->findOrFail($id);

        try {
            $cobranca = $service->executar($cobranca, $dados['pago_em'] ?? null, $dados);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($cobranca);
    }

    /**
     * POST /api/v1/cobrancas/{id}/calcular-juros  { pago_em }
     * Usa valor_principal + vencimento da cobrança (fatura PJ / boleto).
     */
    public function calcularJuros(string $id, Request $request, CalcularJurosMultaService $service): JsonResponse
    {
        $dados = $request->validate([
            'pago_em' => ['required', 'date'],
            'dispensado' => ['sometimes', 'boolean'],
        ]);

        $cobranca = Cobranca::query()->findOrFail($id);

        if ($cobranca->status !== StatusCobranca::Aberta) {
            return response()->json([
                'message' => 'Só é possível calcular juros de cobrança aberta.',
            ], 422);
        }

        $vencimento = $cobranca->vencimento?->toDateString();
        if (! $vencimento) {
            return response()->json(['message' => 'Cobrança sem vencimento.'], 422);
        }

        $calc = $service->calcular(
            (float) $cobranca->valor_principal,
            $vencimento,
            $dados['pago_em'],
            $request->boolean('dispensado')
        );

        return response()->json(array_merge($calc, [
            'cobranca_id' => $cobranca->id,
        ]));
    }

    public function show(string $id): JsonResponse
    {
        $cobranca = Cobranca::query()->with('parcelas')->findOrFail($id);

        return response()->json($cobranca);
    }
}
