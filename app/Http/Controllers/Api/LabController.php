<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Services\Lab\AbrirTodasParcelasContratanteLabService;
use App\Services\Lab\ApagarRemessaLabService;
use App\Services\Lab\LimparFinanceiroContratanteService;
use App\Services\Lab\RegistrarBoletosLabService;
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

    /**
     * DELETE /api/v1/lab/remessas/{id}
     * Apaga remessa + boletos vinculados e devolve parcelas para aberta.
     */
    public function apagarRemessa(string $id, ApagarRemessaLabService $service): JsonResponse
    {
        try {
            $resultado = $service->executar($id);
        } catch (DominioException $e) {
            $status = str_contains($e->getMessage(), 'desabilitada') ? 403 : 404;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json($resultado);
    }

    /**
     * POST /api/v1/lab/parcelas/abrir-todas
     * Promove previstas → abertas no contratante (lab).
     */
    public function abrirTodasParcelas(Request $request, AbrirTodasParcelasContratanteLabService $service): JsonResponse
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

    /**
     * POST /api/v1/lab/registrar-boletos
     * Abre previstas e registra boleto em todas as abertas sem cobrança.
     */
    public function registrarBoletos(Request $request, RegistrarBoletosLabService $service): JsonResponse
    {
        $dados = $request->validate([
            'chave_sigoweb' => ['required', 'string', 'max:64'],
            'vencimento_inicial' => ['required', 'date'],
            'vencimento_final' => ['required', 'date'],
        ]);

        try {
            $resultado = $service->executar(
                $dados['chave_sigoweb'],
                $dados['vencimento_inicial'],
                $dados['vencimento_final'],
            );
        } catch (DominioException $e) {
            $status = str_contains($e->getMessage(), 'desabilitada') ? 403 : 422;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json($resultado, ($resultado['encontrado'] ?? false) ? 200 : 404);
    }
}
