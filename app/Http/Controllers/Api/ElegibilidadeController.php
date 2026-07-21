<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Elegibilidade\ElegibilidadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElegibilidadeController extends Controller
{
    public function __invoke(Request $request, ElegibilidadeService $service): JsonResponse
    {
        $dados = $request->validate([
            'chave_sigoweb' => ['required', 'string', 'max:64'],
            'dias_carencia' => ['nullable', 'integer', 'min:0', 'max:90'],
            'min_parcelas_vencidas' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $resultado = $service->avaliarPorChaveSigoweb(
            $dados['chave_sigoweb'],
            isset($dados['dias_carencia']) ? (int) $dados['dias_carencia'] : null,
            isset($dados['min_parcelas_vencidas']) ? (int) $dados['min_parcelas_vencidas'] : null
        );

        return response()->json($resultado);
    }
}
