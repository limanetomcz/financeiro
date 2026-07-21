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
        ]);

        $resultado = $service->avaliarPorChaveSigoweb(
            $dados['chave_sigoweb'],
            (int) ($dados['dias_carencia'] ?? 0)
        );

        return response()->json($resultado);
    }
}
