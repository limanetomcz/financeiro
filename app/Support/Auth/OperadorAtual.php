<?php

namespace App\Support\Auth;

use App\Support\Tenant\ClienteContext;

/**
 * Operador atual (JWT Sigoweb).
 * Fusca: no futuro emitir evento de auditoria daqui — sem chamar Fusca em ambiente de teste.
 */
class OperadorAtual
{
    /**
     * @return array{login: string, nome: ?string}
     */
    public static function resolver(?array $override = null): array
    {
        if ($override !== null) {
            return [
                'login' => (string) ($override['login'] ?? 'sistema'),
                'nome' => isset($override['nome']) ? (string) $override['nome'] : null,
            ];
        }

        $usuario = ClienteContext::usuario() ?? [];
        $payload = $usuario['payload'] ?? [];

        if (is_object($payload)) {
            $payload = (array) $payload;
        }

        $login = $usuario['login']
            ?? $payload['login']
            ?? $payload['sub']
            ?? null;

        $nome = $usuario['nome']
            ?? $payload['nome']
            ?? $payload['usu_nome']
            ?? null;

        return [
            'login' => $login ? (string) $login : 'sistema',
            'nome' => $nome ? (string) $nome : null,
        ];
    }
}
