<?php

namespace App\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use UnexpectedValueException;

class SigowebJwtValidator
{
    /**
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        $secret = config('sigoweb.jwt_secret');
        $algo = config('sigoweb.jwt_algo', 'HS256');

        if (! $secret) {
            throw new InvalidArgumentException('SIGOWEB_JWT_SECRET não configurado.');
        }

        try {
            $payload = JWT::decode($token, new Key($secret, $algo));
        } catch (\Throwable $e) {
            throw new UnexpectedValueException('Token JWT inválido: '.$e->getMessage(), previous: $e);
        }

        return (array) $payload;
    }

    public function extrairChaveCliente(array $payload): ?string
    {
        if (! empty($payload['par_coop'])) {
            return (string) $payload['par_coop'];
        }

        if (isset($payload['parametros']) && is_object($payload['parametros']) && ! empty($payload['parametros']->par_coop)) {
            return (string) $payload['parametros']->par_coop;
        }

        if (isset($payload['parametros']) && is_array($payload['parametros']) && ! empty($payload['parametros']['par_coop'])) {
            return (string) $payload['parametros']['par_coop'];
        }

        return null;
    }
}
