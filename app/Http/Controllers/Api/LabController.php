<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Services\Lab\LimparFinanceiroContratanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabController extends Controller
{
    /**
     * DELETE /api/v1/lab/financeiro?chave_sigoweb=
     * Só com FINANCEIRO_LAB_LIMPEZA=true (local/teste).
     */
    public function limparFinanceiro(Request $request, LimparFinanceiroContratanteService $service): JsonResponse
    {
        $dados = $request->validate([
            'chave_sigoweb' => ['required', 'string', 'max:64'],
        ]);

        try {
            $resultado = $service->porChaveSigoweb($dados['chave_sigoweb']);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json($resultado);
    }
}
