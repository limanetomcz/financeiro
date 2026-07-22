<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Services\Parcela\AbrirParcelasExigiveisService;
use App\Services\Parcela\BaixarParcelaService;
use App\Services\Parcela\ListarParcelasContratanteService;
use App\Services\Parcela\RetirarBaixaParcelaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParcelaController extends Controller
{
    /**
     * Grid de parcelas do contratante (equivalente ao grid de mensalidades do Sigoweb).
     * GET /api/v1/parcelas?chave_sigoweb=
     */
    public function index(Request $request, ListarParcelasContratanteService $service): JsonResponse
    {
        $dados = $request->validate([
            'chave_sigoweb' => ['required', 'string', 'max:64'],
        ]);

        $resultado = $service->porChaveSigoweb($dados['chave_sigoweb']);
        $status = ($resultado['encontrado'] ?? false) ? 200 : 404;

        return response()->json($resultado, $status);
    }

    public function abrirExigiveis(Request $request, AbrirParcelasExigiveisService $service): JsonResponse
    {
        $dados = $request->validate([
            'referencia' => ['nullable', 'date'],
        ]);

        $referencia = isset($dados['referencia'])
            ? Carbon::parse($dados['referencia'])
            : null;

        $resultado = $service->executar($referencia);

        return response()->json($resultado);
    }

    /**
     * Baixa manual (caixa/lab).
     * POST /api/v1/parcelas/{id}/baixar
     */
    public function baixar(string $id, Request $request, BaixarParcelaService $service): JsonResponse
    {
        $dados = $request->validate([
            'pago_em' => ['nullable', 'date'],
            'local_pagamento_codigo' => ['nullable', 'string', 'max:10'],
            'codigo_legado' => ['nullable', 'string', 'max:10'],
            'taxa_id' => ['nullable', 'uuid'],
        ]);

        if (empty($dados['codigo_legado']) && empty($dados['local_pagamento_codigo']) && empty($dados['taxa_id'])) {
            return response()->json([
                'message' => 'Informe local_pagamento_codigo, codigo_legado ou taxa_id.',
            ], 422);
        }

        try {
            $cobranca = $service->executar($id, $dados);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($cobranca);
    }

    /**
     * Retira baixa (estorno operacional).
     * POST /api/v1/parcelas/{id}/retirar-baixa
     */
    public function retirarBaixa(string $id, RetirarBaixaParcelaService $service): JsonResponse
    {
        try {
            $cobranca = $service->executar($id);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($cobranca);
    }
}
