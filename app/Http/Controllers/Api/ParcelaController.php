<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Parcela\AbrirParcelasExigiveisService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParcelaController extends Controller
{
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
}
