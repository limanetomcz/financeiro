<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Financeiro\SituacaoFinanceiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SituacaoFinanceiraController extends Controller
{
    /**
     * GET /api/v1/financeiro?chave_sigoweb=...
     *
     * Situação financeira do contratante (PF/PJ) para o Sigoweb.
     */
    public function __invoke(Request $request, SituacaoFinanceiraService $service): JsonResponse
    {
        $dados = $request->validate([
            'chave_sigoweb' => ['required', 'string', 'max:64'],
        ]);

        $resultado = $service->porChaveSigoweb($dados['chave_sigoweb']);

        $status = ($resultado['encontrado'] ?? false) ? 200 : 404;

        return response()->json($resultado, $status);
    }
}
