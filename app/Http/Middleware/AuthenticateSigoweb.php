<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Services\Auth\SigowebJwtValidator;
use App\Support\Tenant\ClienteContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class AuthenticateSigoweb
{
    public function __construct(private SigowebJwtValidator $jwt)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return response()->json(['message' => 'Token ausente.'], 401);
        }

        try {
            $payload = $this->jwt->decode(trim($matches[1]));
        } catch (UnexpectedValueException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $chave = $this->jwt->extrairChaveCliente($payload);

        if (! $chave) {
            return response()->json(['message' => 'Token sem identificação de cooperativa (par_coop).'], 401);
        }

        $cliente = Cliente::findBySigowebKey($chave);

        if (! $cliente) {
            return response()->json([
                'message' => 'Cliente não cadastrado no Financeiro para esta cooperativa.',
                'chave' => $chave,
            ], 403);
        }

        ClienteContext::set($cliente, [
            'login' => $payload['login'] ?? $payload['sub'] ?? null,
            'nome' => $payload['nome'] ?? $payload['usu_nome'] ?? null,
            'tipo_acesso' => $payload['tipo_acesso'] ?? null,
            'payload' => $payload,
        ]);

        $request->attributes->set('cliente', $cliente);
        $request->attributes->set('sigoweb_user', ClienteContext::usuario());

        return $next($request);
    }
}
