<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\RetornoBancario;
use App\Services\Bancario\ProcessarRetornoBancarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetornoBancarioController extends Controller
{
    public function index(): JsonResponse
    {
        $itens = RetornoBancario::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($itens);
    }

    public function store(Request $request, ProcessarRetornoBancarioService $service): JsonResponse
    {
        $request->validate([
            'arquivo' => ['required', 'file', 'max:10240'],
        ]);

        $arquivo = $request->file('arquivo');
        $conteudo = file_get_contents($arquivo->getRealPath());
        if ($conteudo === false || $conteudo === '') {
            return response()->json(['message' => 'Não foi possível ler o arquivo.'], 422);
        }

        try {
            $retorno = $service->executar(
                $conteudo,
                $arquivo->getClientOriginalName() ?: 'retorno.CRT',
            );
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($retorno, 201);
    }

    public function show(string $id): JsonResponse
    {
        $retorno = RetornoBancario::query()->with('itens')->findOrFail($id);

        return response()->json($retorno);
    }
}
