<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Tenant\ClienteContext;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cliente = ClienteContext::get();
        $usuario = ClienteContext::usuario();

        return response()->json([
            'cliente' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'codigo_cooperativa' => $cliente->codigo_cooperativa,
                'chave_sigoweb' => $cliente->chave_sigoweb,
                'usa_financeiro_novo' => $cliente->usa_financeiro_novo,
            ],
            'usuario' => [
                'login' => $usuario['login'] ?? null,
                'tipo_acesso' => $usuario['tipo_acesso'] ?? null,
            ],
        ]);
    }
}
