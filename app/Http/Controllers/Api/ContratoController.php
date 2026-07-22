<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Services\Contrato\CriarContratoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function index(): JsonResponse
    {
        $contratos = Contrato::query()
            ->with(['contratante', 'beneficiarios', 'parcelas'])
            ->latest()
            ->paginate(20);

        return response()->json($contratos);
    }

    public function show(string $id): JsonResponse
    {
        $contrato = Contrato::query()
            ->with(['contratante', 'beneficiarios', 'parcelas.beneficiarios'])
            ->findOrFail($id);

        return response()->json($contrato);
    }

    public function store(Request $request, CriarContratoService $service): JsonResponse
    {
        $dados = $request->validate([
            'contratante.chave_sigoweb' => ['required', 'string', 'max:64'],
            'contratante.tipo' => ['required', 'in:pf,pj'],
            'contratante.nome' => ['required', 'string', 'max:255'],
            'contratante.documento' => ['nullable', 'string', 'max:20'],
            'vigencia_inicio' => ['required', 'date'],
            'vigencia_fim' => ['required', 'date', 'after_or_equal:vigencia_inicio'],
            'valor_total' => ['nullable', 'numeric', 'gt:0'],
            'quantidade_parcelas' => ['nullable', 'integer', 'min:1', 'max:48'],
            'chave_plano_sigoweb' => ['required', 'string', 'max:64'],
            'chave_familia_sigoweb' => ['nullable', 'string', 'max:20'],
            'beneficiarios' => ['nullable', 'array', 'min:1'],
            'beneficiarios.*.chave_sigoweb' => ['required_with:beneficiarios', 'string', 'max:64'],
            'beneficiarios.*.nome' => ['required_with:beneficiarios', 'string', 'max:255'],
            'beneficiarios.*.valor_mensal' => ['required_with:beneficiarios', 'numeric', 'min:0'],
            'beneficiarios.*.documento' => ['nullable', 'string', 'max:20'],
            'beneficiarios.*.tipo_dependencia' => ['nullable', 'in:titular,dependente'],
            'beneficiarios.*.tipodep_sigoweb' => ['nullable', 'string', 'max:10'],
            'beneficiarios.*.chave_depend_sigoweb' => ['nullable', 'string', 'max:10'],
            'codigo' => ['nullable', 'string', 'max:40'],
            'renovado_de_contrato_id' => ['nullable', 'uuid', 'exists:contratos,id'],
            'primeiro_vencimento' => ['nullable', 'date'],
            'perfil_pagamento' => ['nullable', 'in:boleto_parcelado,cartao_parcelado,a_vista'],
            'modo_emissao' => ['nullable', 'in:imediata,escalonada'],
            'modo_geracao' => ['nullable', 'in:mensal_exigivel,todas_abertas'],
            'ja_pago' => ['nullable', 'boolean'],
        ]);

        if (empty($dados['beneficiarios']) && empty($dados['valor_total'])) {
            return response()->json([
                'message' => 'Informe beneficiarios (composição da família) ou valor_total.',
            ], 422);
        }

        try {
            $contrato = $service->executar($dados);
        } catch (DominioException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($contrato, 201);
    }
}
