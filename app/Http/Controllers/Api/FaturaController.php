<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoContratante;
use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Contratante;
use App\Models\Fatura;
use App\Services\Fatura\EmitirCobrancaFaturaPjService;
use App\Services\Fatura\GerarFaturaPjService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaturaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Fatura::query()->with(['contratante', 'lancamentos'])->latest();

        if ($request->filled('contratante_id')) {
            $query->where('contratante_id', $request->string('contratante_id'));
        }

        if ($request->filled('competencia')) {
            $query->where('competencia', $request->string('competencia'));
        }

        return response()->json($query->paginate(20));
    }

    public function show(string $id): JsonResponse
    {
        $fatura = Fatura::query()
            ->with(['contratante', 'lancamentos', 'parcelas', 'cobranca'])
            ->findOrFail($id);

        return response()->json($fatura);
    }

    public function store(Request $request, GerarFaturaPjService $service): JsonResponse
    {
        $dados = $request->validate([
            'contratante_id' => ['required', 'uuid', 'exists:contratantes,id'],
            'competencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'vencimento' => ['nullable', 'date'],
            'lancamentos' => ['nullable', 'array'],
            'lancamentos.*' => ['numeric', 'min:0'],
        ]);

        $empresa = Contratante::query()->findOrFail($dados['contratante_id']);

        if ($empresa->tipo !== TipoContratante::Pj) {
            return response()->json(['message' => 'Contratante precisa ser PJ.'], 422);
        }

        try {
            $fatura = $service->executar(
                $empresa,
                $dados['competencia'],
                $dados['vencimento'] ?? null,
                $dados['lancamentos'] ?? []
            );
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($fatura, 201);
    }

    public function emitirCobranca(string $id, Request $request, EmitirCobrancaFaturaPjService $service): JsonResponse
    {
        $dados = $request->validate([
            'meio' => ['nullable', 'string', 'max:20'],
        ]);

        $fatura = Fatura::query()->findOrFail($id);

        try {
            $cobranca = $service->executar($fatura, $dados['meio'] ?? 'boleto');
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($cobranca, 201);
    }
}
