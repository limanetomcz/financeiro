<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Services\Cobranca\EmitirCobrancaConsolidadaService;
use App\Services\Cobranca\LiquidarCobrancaService;
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
        ]);

        try {
            $cobranca = $service->executar(
                $dados['parcela_ids'],
                $dados['vencimento'],
                $dados['meio'] ?? null
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
        ]);

        $cobranca = Cobranca::query()->findOrFail($id);

        try {
            $cobranca = $service->executar($cobranca, $dados['pago_em'] ?? null);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($cobranca);
    }

    public function show(string $id): JsonResponse
    {
        $cobranca = Cobranca::query()->with('parcelas')->findOrFail($id);

        return response()->json($cobranca);
    }
}
