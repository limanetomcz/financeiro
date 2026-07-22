<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Jobs\GerarRemessaJob;
use App\Models\Remessa;
use App\Services\Bancario\GerarRemessaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RemessaController extends Controller
{
    public function index(): JsonResponse
    {
        $itens = Remessa::query()
            ->orderByDesc('lote')
            ->paginate(20);

        return response()->json($itens);
    }

    public function store(Request $request, GerarRemessaService $service): JsonResponse
    {
        $dados = $request->validate([
            'vencimento_inicial' => ['required', 'date'],
            'vencimento_final' => ['required', 'date'],
            'sincrono' => ['sometimes', 'boolean'],
        ]);

        try {
            $remessa = $service->enfileirar(
                $dados['vencimento_inicial'],
                $dados['vencimento_final'],
            );
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($request->boolean('sincrono')) {
            try {
                $remessa = $service->processar($remessa);
            } catch (DominioException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'remessa' => $remessa->fresh(),
                ], 422);
            }

            return response()->json($remessa, 201);
        }

        GerarRemessaJob::dispatch($remessa->id);

        return response()->json([
            'message' => 'Remessa enfileirada na fila bancario.',
            'remessa' => $remessa,
        ], 202);
    }

    public function show(string $id): JsonResponse
    {
        $remessa = Remessa::query()->with('itens')->findOrFail($id);

        return response()->json($remessa);
    }

    public function download(string $id): StreamedResponse|JsonResponse
    {
        $remessa = Remessa::query()->findOrFail($id);

        if (! $remessa->file_path || ! Storage::disk('local')->exists($remessa->file_path)) {
            return response()->json(['message' => 'Arquivo de remessa ainda não disponível.'], 404);
        }

        return Storage::disk('local')->download(
            $remessa->file_path,
            $remessa->file_name ?? 'remessa.CRM'
        );
    }
}
