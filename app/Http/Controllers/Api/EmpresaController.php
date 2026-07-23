<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoContratante;
use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Contratante;
use App\Services\Empresa\UpsertEmpresaPjService;
use App\Services\Empresa\VincularBeneficiarioEmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $chave = trim((string) $request->query('chave_sigoweb', ''));
        if ($chave === '') {
            return response()->json(['message' => 'Informe chave_sigoweb.'], 422);
        }

        $empresa = Contratante::query()
            ->where('chave_sigoweb', $chave)
            ->where('tipo', TipoContratante::Pj)
            ->with(['beneficiarios' => fn ($q) => $q->orderBy('nome')])
            ->first();

        if (! $empresa) {
            return response()->json(['encontrado' => false, 'message' => 'Empresa não encontrada.'], 404);
        }

        return response()->json([
            'encontrado' => true,
            'empresa' => $empresa,
            'beneficiarios_qtd' => $empresa->beneficiarios->count(),
        ]);
    }

    public function store(Request $request, UpsertEmpresaPjService $service): JsonResponse
    {
        $dados = $request->validate([
            'chave_sigoweb' => ['required', 'string', 'max:64'],
            'nome' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:80'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'cep' => ['nullable', 'string', 'max:10'],
            'uf' => ['nullable', 'string', 'max:2'],
        ]);

        try {
            $empresa = $service->executar($dados);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($empresa, 201);
    }

    public function vincularBeneficiario(
        string $id,
        Request $request,
        VincularBeneficiarioEmpresaService $service
    ): JsonResponse {
        $dados = $request->validate([
            'chave_sigoweb' => ['required', 'string', 'max:64'],
            'nome' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:20'],
        ]);

        $empresa = Contratante::query()->findOrFail($id);

        try {
            $pf = $service->executar($empresa, $dados);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($pf);
    }
}
